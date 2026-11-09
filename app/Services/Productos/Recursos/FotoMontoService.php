<?php

namespace App\Services\Productos\Recursos;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\CatalogoProductos;
use App\Models\FotoMontos;
use App\Services\Productos\FotosProductoFtp;
use App\Services\Soporte\Transacciones;
use App\Services\Soporte\ValidaDatos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Foto (gif) de los montos de un producto digital
 */
class FotoMontoService
{
    use ValidaDatos;

    /** Carpeta del FTP donde Brimagy busca los gifs de detalle. */
    const CARPETA_FTP = 'Gifs/detalles';

    private $transacciones;
    private $ftp;

    public function __construct(Transacciones $transacciones, FotosProductoFtp $ftp)
    {
        $this->transacciones = $transacciones;
        $this->ftp = $ftp;
    }

    /** @return Collection Las fotos de monto del producto, igual que antes */
    public function subir(array $datos): Collection
    {
        $this->validar($datos, [
            'id_producto' => 'required',
            'id_producto_brimagy' => 'nullable',
            'foto_monto' => FotosRecursoService::REGLAS_ARCHIVO,
        ]);

        $producto = CatalogoProductos::find($datos['id_producto']);
        if (!$producto) {
            throw new RecursoNoEncontradoException('Este producto no existe');
        }

        /** @var UploadedFile $archivo */
        $archivo = $datos['foto_monto'];
        // El nombre es el del producto a propósito (así lo busca la plataforma); no se usa nombreUnico()
        $nombre = $producto->nombre_producto . '.' . $archivo->getClientOriginalExtension();
        $existente = FotoMontos::where('id_producto', $producto->id)->first();

        try {
            $this->transacciones->en([], function () use ($archivo, $nombre, $producto, $existente) {
                $ruta = $this->ftp->subir($archivo, self::CARPETA_FTP, $nombre, 'foto_monto/' . $producto->id);

                if ($existente) {
                    if ($existente->url_foto && $existente->url_foto !== $ruta) {
                        $this->ftp->programarBorradoPrivado($existente->url_foto);
                    }
                    if ($existente->nombre && $existente->nombre !== $nombre) {
                        $this->ftp->programarBorrado(self::CARPETA_FTP, $existente->nombre);
                    }

                    $existente->update([
                        'nombre' => $nombre,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'url_foto' => $ruta,
                    ]);

                    return;
                }

                FotoMontos::create([
                    'id_producto' => $producto->id,
                    'nombre' => $nombre,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'url_foto' => $ruta,
                    'status' => RecursoProductoService::ACTIVO,
                ]);
            });
        } catch (\Throwable $th) {
            $this->ftp->descartarBorradosPendientes();
            throw $th;
        }

        $this->ftp->ejecutarBorradosPendientes();

        return FotoMontos::where('id_producto', $producto->id)->get();
    }
}
