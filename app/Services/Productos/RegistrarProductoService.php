<?php

namespace App\Services\Productos;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\CatalogoProductos;
use App\Models\CatalogoProveedores;
use App\Models\Colores;
use App\Models\Plataformas;
use App\Models\Tallas;
use App\Models\VariablesGlobales;
use App\Services\Plataformas\PlataformaCatalogo;
use App\Services\Plataformas\PlataformaCatalogoResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso de crearProducto: registra un producto nuevo o, si ya existe uno con el mismo
 * id_producto_brimagy (carga desde Excel), lo actualiza.
 */
class RegistrarProductoService
{
    use NormalizaDatosProducto;

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
     * @param array $datos  Campos del formulario (sin el archivo)
     * @return array{producto: CatalogoProductos, actualizado: bool}
     */
    public function registrar(array $datos, ?UploadedFile $foto, int $idUsuario): array
    {
        DB::beginTransaction();

        try {
            $resultado = $this->registrarEnTransaccion($datos, $foto, $idUsuario);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $this->fotos->descartarBorradosPendientes();
            throw $th;
        }

        $this->fotos->ejecutarBorradosPendientes();

        return $resultado;
    }

    private function registrarEnTransaccion(array $datos, ?UploadedFile $foto, int $idUsuario): array
    {
        $ref = $this->resolverReferencias($datos);
        $variables = VariablesGlobales::where('id_plataforma', $ref['plataforma']->id)->first();

        // La foto solo se sube en el registro individual (en Excel no viene archivo)
        $carpeta = null;
        $nombreFoto = null;
        if (($datos['tipo_registro'] ?? null) === 'individual' && $foto) {
            $carpeta = $ref['catalogo']->carpetaFtp($ref['id_catalogo']);
            if (!$carpeta) {
                throw new RecursoNoEncontradoException('La categoría del producto no existe');
            }
            $nombreFoto = $this->fotos->nombreUnico($foto);
        }

        $existente = $this->buscarExistente($datos['id_producto_brimagy'] ?? null);

        $producto = $existente
            ? $this->actualizarExistente($existente, $datos, $ref, $variables, $nombreFoto, $idUsuario)
            : $this->crearNuevo($datos, $ref, $variables, $nombreFoto, $idUsuario);

        if ($foto && $nombreFoto) {
            $this->fotos->subir($foto, $carpeta, $nombreFoto);
        }

        return ['producto' => $producto, 'actualizado' => (bool) $existente];
    }

    /**
     * Plataforma, categoría y proveedor del producto. En Excel llegan por nombre;
     * en el registro individual, por id.
     */
    private function resolverReferencias(array $datos): array
    {
        $idProveedor = $this->idOpcional($datos['id_proveedor'] ?? null);
        $idCatalogo = $this->idOpcional($datos['id_catalogo'] ?? null);

        if (($datos['tipo_registro'] ?? null) !== 'excel') {
            $plataforma = Plataformas::find($this->idOpcional($datos['id_plataforma'] ?? null));
            if (!$plataforma) {
                throw new RecursoNoEncontradoException('La plataforma no existe');
            }

            return [
                'plataforma' => $plataforma,
                'catalogo' => $this->plataformas->para($plataforma->nombre),
                'id_catalogo' => $idCatalogo,
                'id_proveedor' => $idProveedor,
            ];
        }

        if ($this->relleno($datos, 'proveedor')) {
            $proveedor = CatalogoProveedores::where('nombre', $datos['proveedor'])->first();
            if (!$proveedor) {
                throw new RecursoNoEncontradoException('El proveedor "' . $datos['proveedor'] . '" no existe');
            }
            $idProveedor = $proveedor->id;
        }

        $nombrePlataforma = $datos['nombre_plataforma'] ?? null;
        $plataforma = Plataformas::where('nombre', $nombrePlataforma)->first();
        if (!$plataforma) {
            throw new RecursoNoEncontradoException('La plataforma "' . $nombrePlataforma . '" no existe');
        }

        $catalogo = $this->plataformas->para($plataforma->nombre);
        $subcategoria = $catalogo->buscarSubcategoriaPorNombre($datos['catalogo'] ?? null);
        if (!$subcategoria) {
            throw new RecursoNoEncontradoException('La categoría "' . ($datos['catalogo'] ?? '') . '" no existe');
        }

        return [
            'plataforma' => $plataforma,
            'catalogo' => $catalogo,
            'id_catalogo' => $subcategoria->id,
            'id_proveedor' => $idProveedor,
        ];
    }

    /** Solo busca si viene un id: antes un id vacío podía empatar con cualquier producto sin id Brimagy. */
    private function buscarExistente($idProductoBrimagy): ?CatalogoProductos
    {
        $id = $this->idOpcional($idProductoBrimagy);

        return $id === null ? null : CatalogoProductos::where('id_producto_brimagy', $id)->first();
    }

    private function crearNuevo(array $datos, array $ref, $variables, ?string $nombreFoto, int $idUsuario): CatalogoProductos
    {
        /** @var PlataformaCatalogo $catalogo */
        $catalogo = $ref['catalogo'];

        $parametros = $this->costosBase($variables, $catalogo->valoresPorDefecto())
            + $this->envioYFactor($datos, $variables, $catalogo);

        $calculo = $this->calcular($datos['costo_con_iva'] ?? null, $datos['costo_puntos_con_iva'] ?? null, $parametros);

        $idRemoto = $catalogo->crearProductoRemotoSiNoExiste(
            $this->idOpcional($datos['id_producto_brimagy'] ?? null),
            $this->datosRemotos($datos, $ref['id_catalogo'], $calculo) + ['photo_name' => $nombreFoto ?? '']
        );

        $producto = CatalogoProductos::create($this->datosLocales($datos, $ref) + $calculo + [
            'foto_producto' => $nombreFoto ?? '',
            'id_producto_brimagy' => $idRemoto,
        ]);

        $this->agregarColorYTalla($catalogo, $datos, $producto->id, $idRemoto);
        $this->bitacora->registrarCreacion($idUsuario, $producto->id);

        return $producto;
    }

    private function actualizarExistente(
        CatalogoProductos $producto,
        array $datos,
        array $ref,
        $variables,
        ?string $nombreFoto,
        int $idUsuario
    ): CatalogoProductos {
        /** @var PlataformaCatalogo $catalogo */
        $catalogo = $ref['catalogo'];
        $anteriores = $producto->only(CalculadoraPuntos::camposAuditables());

        $porDefecto = $catalogo->valoresPorDefecto();
        // Sin variables, el Excel puede traer su propio fee (igual que antes)
        if (!$variables && isset($datos['fee_brimagy'])) {
            $porDefecto['fee_brimagy'] = $datos['fee_brimagy'];
        }
        $parametros = $this->costosBase($variables, $porDefecto)
            + $this->envioYFactor($datos, $variables, $catalogo);

        $calculo = $this->calcular(
            $datos['costo_con_iva'] ?? $producto->costo_con_iva,
            $datos['costo_puntos_con_iva'] ?? $producto->costo_puntos_con_iva,
            $parametros
        );

        $producto->update($this->datosLocales($datos, $ref) + $calculo + [
            'foto_producto' => $nombreFoto ?? $producto->foto_producto,
            'id_producto_brimagy' => $producto->id_producto_brimagy,
            'updated_at' => now()->setTimezone('America/Mexico_City'),
        ]);

        // Los campos que no vienen (null) conservan el valor que ya tiene la plataforma
        $remotos = $this->datosRemotos($datos, $ref['id_catalogo'], $calculo);
        if ($nombreFoto) {
            $remotos['photo_name'] = $nombreFoto;
        }
        $catalogo->actualizarProductoRemoto(
            $producto->id_producto_brimagy,
            array_filter($remotos, function ($valor) {
                return $valor !== null;
            })
        );

        $this->agregarColorYTalla($catalogo, $datos, $producto->id, (int) $producto->id_producto_brimagy);
        $this->bitacora->registrarEdicion(
            $idUsuario,
            $producto->id,
            $anteriores,
            $calculo,
            'Sin cambios en valores numéricos'
        );

        return $producto;
    }

    /** fee_brimagy, envio_base y costo_caja: de las variables o, si no hay, los valores por defecto. */
    private function costosBase($variables, array $porDefecto): array
    {
        return [
            'fee_brimagy' => $variables ? $variables->fee_brimagy : $porDefecto['fee_brimagy'],
            'envio_base' => $variables ? $variables->envio_base : $porDefecto['envio_base'],
            'costo_caja' => $variables ? $variables->costo_caja : $porDefecto['costo_caja'],
        ];
    }

    /** envio_extra y valor_factor según el origen del registro (Excel o individual). */
    private function envioYFactor(array $datos, $variables, PlataformaCatalogo $catalogo): array
    {
        $porDefecto = $catalogo->valoresPorDefecto();

        return ($datos['tipo_registro'] ?? null) === 'excel'
            ? $this->reglas->paraExcel($datos, $variables, $porDefecto)
            : $this->reglas->paraIndividual($datos, $variables, $porDefecto);
    }

    private function calcular($costoConIva, $costoPuntosConIva, array $p): array
    {
        return $this->calculadora->calcular(
            $costoConIva,
            $costoPuntosConIva,
            $p['fee_brimagy'],
            $p['envio_base'],
            $p['costo_caja'],
            $p['envio_extra'],
            $p['valor_factor']
        );
    }

    /** Columnas de dc_catalogo_productos que vienen directo del formulario. */
    private function datosLocales(array $datos, array $ref): array
    {
        return [
            'nombre_producto' => $datos['nombre_producto'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'marca' => $datos['marca'] ?? null,
            'sku' => $datos['sku'] ?? null,
            'color' => $datos['color'] ?? null,
            'talla' => $datos['talla'] ?? null,
            'id_proveedor' => $ref['id_proveedor'],
            'id_catalogo' => $ref['id_catalogo'],
            'id_plataforma' => $ref['plataforma']->id,
            'tipo_producto' => $datos['tipo_producto'] ?? null,
        ];
    }

    /** Columnas del producto en la BD de la plataforma. */
    private function datosRemotos(array $datos, $idCatalogo, array $calculo): array
    {
        return [
            'desc' => $datos['nombre_producto'] ?? null,
            'features' => $datos['descripcion'] ?? null,
            'required_score' => $calculo['factor'],
            'sub_category_id' => $idCatalogo,
            'sku' => $datos['sku'] ?? null,
            'TyC' => $datos['tyc'] ?? null,
            'validity' => $datos['vigencia'] ?? null,
        ];
    }

    private function agregarColorYTalla(PlataformaCatalogo $catalogo, array $datos, int $idProducto, int $idRemoto): void
    {
        if ($this->relleno($datos, 'color')) {
            Colores::create([
                'id_producto' => $idProducto,
                'id_color_brimagy' => $catalogo->agregarColor($idRemoto, $datos['color']),
                'color' => $datos['color'],
                'status' => 'ACTIVE',
            ]);
        }

        if ($this->relleno($datos, 'talla')) {
            Tallas::create([
                'id_producto' => $idProducto,
                'id_talla_brimagy' => $catalogo->agregarTalla($idRemoto, $datos['talla']),
                'talla' => $datos['talla'],
                'status' => 'ACTIVE',
            ]);
        }
    }
}
