<?php

namespace App\Services\Productos\Recursos;

use App\Exceptions\RecursoNoEncontradoException;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoProductos;
use App\Services\Plataformas\PlataformaCatalogo;
use App\Services\Plataformas\PlataformaCatalogoResolver;
use App\Services\Productos\BitacoraProductos;
use App\Services\Productos\NormalizaDatosProducto;
use App\Services\Soporte\Transacciones;
use App\Services\Soporte\ValidaDatos;
use Illuminate\Database\Eloquent\Model;

/**
 * Alta, edición, activación y desactivación de cualquier recurso del producto
 * (colores, tallas, montos, fotos). Lo que cambia entre recursos viene en RecursoProducto
 * y lo que cambia entre plataformas, en PlataformaCatalogo.
 */
class RecursoProductoService
{
    use NormalizaDatosProducto;
    use ValidaDatos;

    const ACTIVO = 'ACTIVE';
    const INACTIVO = 'INACTIVE';

    private $plataformas;
    private $transacciones;
    private $bitacora;

    public function __construct(
        PlataformaCatalogoResolver $plataformas,
        Transacciones $transacciones,
        BitacoraProductos $bitacora
    ) {
        $this->plataformas = $plataformas;
        $this->transacciones = $transacciones;
        $this->bitacora = $bitacora;
    }

    /**
     * Crea el recurso o, si viene su id y existe, lo edita.
     *
     * @return array{registro: Model, editado: bool}
     */
    public function guardar(RecursoProducto $recurso, array $datos, int $idUsuario): array
    {
        $reglas = $recurso->reglasCampos() + [
            'id_producto_brimagy' => 'nullable|integer',
            'id_producto_dirac' => 'nullable|integer',
            $recurso->campoId() => 'nullable|integer',
        ];
        if ($recurso->campoIdRemoto()) {
            $reglas[$recurso->campoIdRemoto()] = 'nullable|integer';
        }
        $this->validar($datos, $reglas);

        $plataforma = $this->plataformas->desdeParametro($datos['plataforma'] ?? null);

        return $this->transacciones->en($plataforma->conexiones(), function () use ($recurso, $datos, $plataforma, $idUsuario) {
            $modelo = $recurso->modeloLocal();
            $idLocal = $this->idOpcional($datos[$recurso->campoId()] ?? null);
            $existente = $idLocal ? $modelo::find($idLocal) : null;

            $valores = [];
            foreach (array_keys($recurso->campos()) as $campo) {
                $valores[$campo] = $datos[$campo] ?? null;
            }

            $idProducto = $this->idOpcional($datos['id_producto_dirac'] ?? null)
                ?? ($existente ? $existente->id_producto : null);
            $this->validarQueNoSeRepita($recurso, $valores, $idProducto, $existente);

            if ($existente) {
                return [
                    'registro' => $this->editar($recurso, $plataforma, $existente, $valores, $datos, $idUsuario),
                    'editado' => true,
                ];
            }

            return [
                'registro' => $this->crear($recurso, $plataforma, $idProducto, $valores, $datos, $idUsuario),
                'editado' => false,
            ];
        });
    }

    /** Activa (ACTIVE) o desactiva (INACTIVE) el recurso en local y en la plataforma. */
    public function cambiarEstatus(RecursoProducto $recurso, array $datos, bool $activar, int $idUsuario): Model
    {
        $reglas = [$recurso->campoId() => 'required|integer'];
        if ($recurso->campoIdRemoto()) {
            $reglas[$recurso->campoIdRemoto()] = 'nullable|integer';
        }
        $this->validar($datos, $reglas);

        $plataforma = $this->plataformas->desdeParametro($datos['plataforma'] ?? null);
        $status = $activar ? self::ACTIVO : self::INACTIVO;

        return $this->transacciones->en($plataforma->conexiones(), function () use ($recurso, $datos, $plataforma, $status, $activar, $idUsuario) {
            $modelo = $recurso->modeloLocal();
            $local = $modelo::find($datos[$recurso->campoId()]);
            if (!$local) {
                throw new RecursoNoEncontradoException($recurso->mensajeNoEncontrado());
            }

            if ($recurso->claveRemota()) {
                $idRemoto = $this->idRemoto($recurso, $local, $datos);
                $this->exigirRemoto($recurso, $plataforma, $idRemoto);
                $plataforma->actualizarRegistroRemoto($recurso->claveRemota(), $idRemoto, ['status' => $status]);
            }

            $local->update(['status' => $status]);

            $verbo = $activar ? 'activó' : 'desactivó';
            $this->bitacora->registrar(
                "Se {$verbo} " . $recurso->conIndefinido() . ' de ' . $recurso->ambito(),
                $recurso->tablaBitacora(),
                $local->id_producto,
                "El usuario con id: {$idUsuario} {$verbo} " . $recurso->conArticulo() . ' ' . $local->{$recurso->campoDescriptivo()},
                $idUsuario
            );

            return $local;
        });
    }

    private function crear(
        RecursoProducto $recurso,
        PlataformaCatalogo $plataforma,
        $idProducto,
        array $valores,
        array $datos,
        int $idUsuario
    ): Model {
        $local = ['id_producto' => $idProducto] + $valores + ['status' => self::ACTIVO];

        if ($recurso->claveRemota()) {
            $local[$recurso->campoIdRemoto()] = $plataforma->crearRegistroRemoto(
                $recurso->claveRemota(),
                ['award_id' => $this->idPremio($idProducto, $datos)]
                    + $this->aColumnasRemotas($recurso, $valores)
                    + ['status' => self::ACTIVO]
            );
        }

        $modelo = $recurso->modeloLocal();
        $registro = $modelo::create($local);

        $this->bitacora->registrar(
            'Creación de ' . $recurso->nombre(),
            $recurso->tablaBitacora(),
            $idProducto,
            "El usuario con id: {$idUsuario} añadió " . $recurso->conArticulo() . ': '
                . $registro->{$recurso->campoDescriptivo()} . " al producto {$idProducto}",
            $idUsuario
        );

        return $registro;
    }

    private function editar(
        RecursoProducto $recurso,
        PlataformaCatalogo $plataforma,
        Model $local,
        array $valores,
        array $datos,
        int $idUsuario
    ): Model {
        $anteriores = $local->only(array_keys($valores)) + ['status' => $local->status];
        $ahora = now()->setTimezone('America/Mexico_City');

        if ($recurso->claveRemota()) {
            $idRemoto = $this->idRemoto($recurso, $local, $datos);
            $this->exigirRemoto($recurso, $plataforma, $idRemoto);
            $plataforma->actualizarRegistroRemoto(
                $recurso->claveRemota(),
                $idRemoto,
                $this->aColumnasRemotas($recurso, $valores) + ['status' => self::ACTIVO, 'updated_at' => $ahora]
            );
        }

        // Igual que antes: editar también reactiva el registro
        $local->update($valores + ['status' => self::ACTIVO, 'updated_at' => $ahora]);

        $cambios = $this->bitacora->describirCambios($anteriores, $valores + ['status' => self::ACTIVO]);
        $this->bitacora->registrar(
            'Edición de producto',
            $recurso->tablaBitacora(),
            $local->id_producto,
            "Se editó la siguiente información del producto: {$cambios}",
            $idUsuario
        );

        return $local;
    }

    /** El valor del campo único no se puede repetir en el mismo producto (sin contar al propio registro). */
    private function validarQueNoSeRepita(RecursoProducto $recurso, array $valores, $idProducto, ?Model $existente): void
    {
        $campo = $recurso->campoUnico();
        if (!$campo) {
            return;
        }

        $modelo = $recurso->modeloLocal();
        $repetido = $modelo::where($campo, $valores[$campo] ?? null)
            ->where('id_producto', $idProducto)
            ->when($existente, function ($consulta) use ($existente) {
                $consulta->where('id', '!=', $existente->id);
            })
            ->exists();

        if ($repetido) {
            throw new ReglaNegocioException($recurso->mensajeRepetido($valores[$campo]));
        }
    }

    private function exigirRemoto(RecursoProducto $recurso, PlataformaCatalogo $plataforma, $idRemoto): void
    {
        if (!$plataforma->buscarRegistroRemoto($recurso->claveRemota(), $idRemoto)) {
            throw new RecursoNoEncontradoException($recurso->mensajeNoEncontrado());
        }
    }

    /** El id remoto guardado en el registro local manda; el del formulario es respaldo. */
    private function idRemoto(RecursoProducto $recurso, Model $local, array $datos)
    {
        $campo = $recurso->campoIdRemoto();

        return $local->{$campo} ?: $this->idOpcional($datos[$campo] ?? null);
    }

    /** award_id del producto en la plataforma: el guardado en el producto o, si no, el del formulario. */
    private function idPremio($idProducto, array $datos)
    {
        $producto = $idProducto ? CatalogoProductos::find($idProducto) : null;

        return ($producto ? $producto->id_producto_brimagy : null)
            ?: $this->idOpcional($datos['id_producto_brimagy'] ?? null);
    }

    private function aColumnasRemotas(RecursoProducto $recurso, array $valores): array
    {
        $remotos = [];
        foreach ($recurso->campos() as $campo => $columna) {
            $remotos[$columna] = $valores[$campo] ?? null;
        }

        return $remotos;
    }
}
