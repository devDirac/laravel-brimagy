<?php

namespace App\Services\Productos\Recursos;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\CatalogoProductos;
use App\Services\Plataformas\PlataformaCatalogoResolver;
use App\Services\Productos\FotosProductoFtp;
use App\Services\Productos\NormalizaDatosProducto;
use App\Services\Soporte\Transacciones;
use App\Services\Soporte\ValidaDatos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/** Subida de fotos que se guardan en local y en la plataforma (fotos del producto y promocionales). */
class FotosRecursoService
{
    use NormalizaDatosProducto;
    use ValidaDatos;

    const REGLAS_ARCHIVO = 'required|file|mimes:jpg,jpeg,png|max:10240';

    private $plataformas;
    private $transacciones;
    private $ftp;

    public function __construct(
        PlataformaCatalogoResolver $plataformas,
        Transacciones $transacciones,
        FotosProductoFtp $ftp
    ) {
        $this->plataformas = $plataformas;
        $this->transacciones = $transacciones;
        $this->ftp = $ftp;
    }

    /**
     * @param RecursoProducto&SubeArchivos $recurso
     * @param array $datos  Todo el request (incluye los archivos)
     * @return Collection Todas las fotos del producto, igual que antes
     */
    public function subir(RecursoProducto $recurso, array $datos): Collection
    {
        if (!$recurso instanceof SubeArchivos) {
            throw new \InvalidArgumentException(get_class($recurso) . ' no admite subir archivos');
        }

        $campo = $recurso->campoArchivos();
        $this->validar($datos, [
            'id_producto' => 'required',
            'id_producto_brimagy' => 'nullable',
            $campo => 'required|array',
            "{$campo}.*" => self::REGLAS_ARCHIVO,
        ]);

        $producto = CatalogoProductos::find($datos['id_producto']);
        if (!$producto) {
            throw new RecursoNoEncontradoException('Este producto no existe');
        }

        $plataforma = $this->plataformas->desdeParametro($datos['plataforma'] ?? null);
        $carpetaFtp = $recurso->carpetaFtp($plataforma, $producto);
        if (!$carpetaFtp) {
            throw new RecursoNoEncontradoException('La categoría del producto no existe');
        }

        $idPremio = $producto->id_producto_brimagy ?: $this->idOpcional($datos['id_producto_brimagy'] ?? null);
        $modelo = $recurso->modeloLocal();

        $this->transacciones->en($plataforma->conexiones(), function () use ($recurso, $datos, $campo, $plataforma, $carpetaFtp, $producto, $idPremio, $modelo) {
            $usados = [];
            foreach ($datos[$campo] as $archivo) {
                $nombre = $this->nombreSinRepetir($archivo, $usados);
                $ruta = $this->ftp->subir($archivo, $carpetaFtp, $nombre, $recurso->carpetaPrivada());

                $idRemoto = $plataforma->crearRegistroRemoto($recurso->claveRemota(), [
                    'award_id' => $idPremio,
                    $recurso->columnaArchivoRemota() => $nombre,
                    'status' => RecursoProductoService::ACTIVO,
                ]);

                $modelo::create([
                    'id_producto' => $producto->id,
                    $recurso->campoIdRemoto() => $idRemoto,
                    'nombre' => $nombre,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'url_foto' => $ruta,
                ]);
            }
        });

        return $modelo::where('id_producto', $producto->id)->get();
    }

    /**
     * Si suben dos archivos con el mismo nombre en el mismo segundo, el nombre único coincidiría
     * y el segundo sobrescribiría al primero en el FTP. Se agrega _2, _3…
     */
    private function nombreSinRepetir(UploadedFile $archivo, array &$usados): string
    {
        $nombre = $this->ftp->nombreUnico($archivo);
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $extension = pathinfo($nombre, PATHINFO_EXTENSION);

        $candidato = $nombre;
        $n = 2;
        while (in_array($candidato, $usados, true)) {
            $candidato = "{$base}_{$n}.{$extension}";
            $n++;
        }

        $usados[] = $candidato;

        return $candidato;
    }
}
