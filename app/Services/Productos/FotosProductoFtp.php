<?php

namespace App\Services\Productos;

use App\Models\FotosProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos del producto en el FTP de Brimagy.
 *
 * Las subidas se hacen dentro de la transacción: si fallan, se hace rollback y a lo mucho
 * queda un archivo huérfano. Los borrados solo se programan y se ejecutan con
 * ejecutarBorradosPendientes() después del commit, para que un rollback nunca deje a la BD
 * apuntando a una foto que ya se borró.
 */
class FotosProductoFtp
{
    const DISCO = 'ftp_brimagy';

    /** @var array<int, string> */
    private $borradosPendientes = [];

    /** @var array<int, string> */
    private $borradosPrivadosPendientes = [];

    /** "Mi Foto Café.JPG" → "mi_foto_cafe_2026-11-06_11_42_00.jpg" (sin espacios, acentos ni símbolos). */
    public function nombreUnico(UploadedFile $archivo): string
    {
        $sinExtension = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
        $limpio = Str::slug($sinExtension, '_') ?: 'foto';
        $extension = strtolower($archivo->getClientOriginalExtension());

        return $limpio . '_' . now()->format('Y-m-d_H_i_s') . '.' . $extension;
    }

    /**
     * Guarda una copia en el disco privado y sube el archivo a $carpeta en el FTP.
     * Regresa la ruta en el disco privado.
     */
    public function subir(
        UploadedFile $archivo,
        string $carpeta,
        string $nombre,
        string $carpetaPrivada = 'fotos_producto'
    ): string {
        $ruta = $archivo->storeAs($carpetaPrivada, $nombre, 'private');
        $this->disco()->put($carpeta . '/' . $nombre, file_get_contents($archivo->getRealPath()));

        return $ruta;
    }

    /** Copia la foto a la nueva carpeta ahora y borra la original después del commit. */
    public function mover(string $carpetaOrigen, string $carpetaDestino, ?string $nombre): void
    {
        if (empty($nombre) || $carpetaOrigen === $carpetaDestino) {
            return;
        }

        $origen = $carpetaOrigen . '/' . $nombre;
        if ($this->disco()->exists($origen)) {
            $this->disco()->put($carpetaDestino . '/' . $nombre, $this->disco()->get($origen));
            $this->programarBorrado($carpetaOrigen, $nombre);
        }
    }

    /** Mueve las fotos adicionales del producto a la carpeta de su nueva categoría. */
    public function moverFotosAdicionales(int $idProducto, string $carpetaOrigen, string $carpetaDestino): void
    {
        if ($carpetaOrigen === $carpetaDestino) {
            return;
        }

        foreach (FotosProducto::where('id_producto', $idProducto)->get() as $foto) {
            $this->mover($carpetaOrigen, $carpetaDestino, $foto->nombre);
        }
    }

    public function programarBorrado(string $carpeta, ?string $nombre): void
    {
        if (!empty($nombre)) {
            $this->borradosPendientes[] = $carpeta . '/' . $nombre;
        }
    }

    /** Programa el borrado de un archivo del disco privado (ej. la versión anterior de una foto). */
    public function programarBorradoPrivado(?string $ruta): void
    {
        if (!empty($ruta)) {
            $this->borradosPrivadosPendientes[] = $ruta;
        }
    }

    /** Llamar después de DB::commit(). Un fallo aquí solo deja archivos huérfanos. */
    public function ejecutarBorradosPendientes(): void
    {
        foreach ($this->borradosPendientes as $ruta) {
            try {
                if ($this->disco()->exists($ruta)) {
                    $this->disco()->delete($ruta);
                }
            } catch (\Throwable $th) {
                report($th);
            }
        }

        $this->borradosPendientes = [];

        foreach ($this->borradosPrivadosPendientes as $ruta) {
            try {
                if (Storage::disk('private')->exists($ruta)) {
                    Storage::disk('private')->delete($ruta);
                }
            } catch (\Throwable $th) {
                report($th);
            }
        }

        $this->borradosPrivadosPendientes = [];
    }

    /** Llamar en el rollback: lo programado ya no debe borrarse. */
    public function descartarBorradosPendientes(): void
    {
        $this->borradosPendientes = [];
        $this->borradosPrivadosPendientes = [];
    }

    private function disco()
    {
        return Storage::disk(self::DISCO);
    }
}
