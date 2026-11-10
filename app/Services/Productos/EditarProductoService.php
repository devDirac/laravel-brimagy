<?php

namespace App\Services\Productos;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\CatalogoProductos;
use App\Models\Plataformas;
use App\Models\ProductoEditado;
use App\Models\VariablesGlobales;
use App\Services\Plataformas\PlataformaCatalogoResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EditarProductoService
{
    use NormalizaDatosProducto;

    /** Campos de dc_catalogo_productos que se pueden editar desde el formulario. */
    const CAMPOS_EDITABLES = [
        'nombre_producto',
        'descripcion',
        'marca',
        'sku',
        'color',
        'talla',
        'id_proveedor',
        'id_catalogo',
        'costo_con_iva',
        'costo_puntos_con_iva',
        'envio_extra',
        'valor_factor',
    ];

    /** Campos que solo existen en la plataforma */
    const CAMPOS_SOLO_REMOTOS = ['tyc' => 'TyC', 'vigencia' => 'validity'];

    private $calculadora;
    private $reglas;
    private $plataformas;
    private $fotos;
    private $bitacora;

    public function __construct(
        CalculadoraPuntos $calculadora,
        ReglasEnvioYFactor $reglas,
        PlataformaCatalogoResolver $plataformas,
        FotosProductoFtp $fotos,
        BitacoraProductos $bitacora
    ) {
        $this->calculadora = $calculadora;
        $this->reglas = $reglas;
        $this->plataformas = $plataformas;
        $this->fotos = $fotos;
        $this->bitacora = $bitacora;
    }

    /**
     * @return bool false si no había nada que cambiar
     */
    public function editar(array $datos, ?UploadedFile $foto, int $idUsuario): bool
    {
        DB::beginTransaction();

        try {
            $huboCambios = $this->editarEnTransaccion($datos, $foto, $idUsuario);
            $huboCambios ? DB::commit() : DB::rollBack();
        } catch (\Throwable $th) {
            DB::rollBack();
            $this->fotos->descartarBorradosPendientes();
            throw $th;
        }

        $this->fotos->ejecutarBorradosPendientes();

        return $huboCambios;
    }

    private function editarEnTransaccion(array $datos, ?UploadedFile $foto, int $idUsuario): bool
    {
        $producto = CatalogoProductos::find($datos['id_producto'] ?? null);
        if (!$producto) {
            throw new RecursoNoEncontradoException('Este producto no existe');
        }

        foreach (['id_proveedor', 'id_catalogo'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $datos[$campo] = $this->idOpcional($datos[$campo]);
            }
        }

        // Las variables se toman de la plataforma que manda el formulario
        // y los valores por defecto, de la plataforma del producto
        $nombrePlataforma = $datos['nombre_plataforma'] ?? null;
        $plataformaSolicitada = Plataformas::where('nombre', $nombrePlataforma)->first();
        if (!$plataformaSolicitada) {
            throw new RecursoNoEncontradoException('La plataforma "' . $nombrePlataforma . '" no existe');
        }
        $variables = VariablesGlobales::where('id_plataforma', $plataformaSolicitada->id)->first();

        $plataforma = Plataformas::find($producto->id_plataforma);
        $catalogo = $this->plataformas->para($plataforma ? $plataforma->nombre : null);

        // Se usa el id de brimagy guardado en el producto
        $idRemoto = $producto->id_producto_brimagy ?: $this->idOpcional($datos['id_producto_brimagy'] ?? null);
        $remoto = $catalogo->buscarProductoRemoto($idRemoto);
        if (!$remoto) {
            throw new RecursoNoEncontradoException('No se encuentra el producto brimagy');
        }

        $cambiosLocales = $this->cambios($producto, $datos);
        if (empty($cambiosLocales) && !$this->cambiaronCamposRemotos($remoto, $datos) && !$foto) {
            return false;
        }

        $this->marcarEditadoManualmente($producto, $datos);

        $valor = function (string $campo) use ($datos, $producto) {
            return array_key_exists($campo, $datos) ? $datos[$campo] : $producto->$campo;
        };
        $porDefecto = $catalogo->valoresPorDefecto();

        $envioYFactor = $this->reglas->paraEdicion($datos, $producto, $variables, $porDefecto);

        $calculo = $this->calculadora->calcular(
            $valor('costo_con_iva'),
            $valor('costo_puntos_con_iva'),
            $variables ? $variables->fee_brimagy : $porDefecto['fee_brimagy'],
            $variables ? $variables->envio_base : $porDefecto['envio_base'],
            $variables ? $variables->costo_caja : $porDefecto['costo_caja'],
            $envioYFactor['envio_extra'],
            $envioYFactor['valor_factor']
        );

        $nombreFoto = $this->actualizarFotos($catalogo, $producto, $datos, $foto);

        $anteriores = $producto->only(CalculadoraPuntos::camposAuditables());
        $actualizar = array_merge($cambiosLocales, $calculo, [
            'updated_at' => now()->setTimezone('America/Mexico_City'),
        ]);
        if ($nombreFoto !== null) {
            $actualizar['foto_producto'] = $nombreFoto;
        }
        $producto->update($actualizar);

        $catalogo->actualizarProductoRemoto($idRemoto, $this->datosRemotos($datos, $calculo, $nombreFoto));

        $this->bitacora->registrarEdicion(
            $idUsuario,
            $producto->id,
            $anteriores,
            $calculo,
            'Solo se editaron campos de texto'
        );

        return true;
    }

    /** Campos editables que vienen en el formulario y son distintos a los guardados. */
    private function cambios(CatalogoProductos $producto, array $datos): array
    {
        $cambios = [];
        foreach (self::CAMPOS_EDITABLES as $campo) {
            if (array_key_exists($campo, $datos) && !$this->mismoValor($producto->$campo, $datos[$campo])) {
                $cambios[$campo] = $datos[$campo];
            }
        }

        return $cambios;
    }

    private function cambiaronCamposRemotos($remoto, array $datos): bool
    {
        foreach (self::CAMPOS_SOLO_REMOTOS as $campo => $columna) {
            if (array_key_exists($campo, $datos) && !$this->mismoValor($remoto->$columna, $datos[$campo])) {
                return true;
            }
        }

        return false;
    }

    private function marcarEditadoManualmente(CatalogoProductos $producto, array $datos): void
    {
        if ($this->relleno($datos, 'envio_extra') || $this->relleno($datos, 'valor_factor')) {
            ProductoEditado::firstOrCreate(['id_producto' => $producto->id], ['editado' => 1]);
        }
    }

    /**
     * Sube la foto nueva o mueve la actual si cambió la categoría.
     * Regresa el nombre de la foto si hay que guardarlo, o null si no cambió.
     */
    private function actualizarFotos($catalogo, CatalogoProductos $producto, array $datos, ?UploadedFile $foto): ?string
    {
        $idCatalogoNuevo = $datos['id_catalogo'] ?? $producto->id_catalogo;
        $carpetaAnterior = $catalogo->carpetaFtp($producto->id_catalogo);
        $carpetaNueva = $catalogo->carpetaFtp($idCatalogoNuevo);
        $cambioCarpeta = $carpetaAnterior && $carpetaNueva && $carpetaAnterior !== $carpetaNueva;

        if ($foto) {
            if (!$carpetaAnterior || !$carpetaNueva) {
                throw new RecursoNoEncontradoException('La categoría del producto no existe');
            }

            $nombre = $this->fotos->nombreUnico($foto);
            $this->fotos->subir($foto, $carpetaNueva, $nombre);
            $this->fotos->programarBorrado($carpetaAnterior, $producto->foto_producto);

            if ($cambioCarpeta) {
                $this->fotos->moverFotosAdicionales($producto->id, $carpetaAnterior, $carpetaNueva);
            }

            return $nombre;
        }

        if ($cambioCarpeta && !empty($producto->foto_producto)) {
            $this->fotos->mover($carpetaAnterior, $carpetaNueva, $producto->foto_producto);
            $this->fotos->moverFotosAdicionales($producto->id, $carpetaAnterior, $carpetaNueva);

            return $producto->foto_producto;
        }

        return null;
    }

    /** Solo se mandan los campos que vienen en el formulario; los demás conservan su valor remoto. */
    private function datosRemotos(array $datos, array $calculo, ?string $nombreFoto): array
    {
        $mapa = [
            'nombre_producto' => 'desc',
            'descripcion' => 'features',
            'id_catalogo' => 'sub_category_id',
            'sku' => 'sku',
            'tyc' => 'TyC',
            'vigencia' => 'validity',
        ];

        $remotos = ['required_score' => $calculo['factor']];
        foreach ($mapa as $campo => $columna) {
            if (array_key_exists($campo, $datos)) {
                $remotos[$columna] = $datos[$campo];
            }
        }
        if ($nombreFoto !== null) {
            $remotos['photo_name'] = $nombreFoto;
        }

        return $remotos;
    }
}
