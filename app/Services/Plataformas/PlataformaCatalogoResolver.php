<?php

namespace App\Services\Plataformas;

use App\Exceptions\RecursoNoEncontradoException;
use Illuminate\Contracts\Container\Container;

/** Entrega la implementación de PlataformaCatalogo según el nombre de la plataforma. */
class PlataformaCatalogoResolver
{
    /** nombre en la tabla plataformas => clase */
    const PLATAFORMAS = [
        'puntotes' => PuntotesCatalogo::class,
        'club bohn' => ClubBohnCatalogo::class,
    ];

    private $contenedor;

    public function __construct(Container $contenedor)
    {
        $this->contenedor = $contenedor;
    }

    /** Por el nombre exacto de la tabla plataformas ("puntotes", "club bohn"). */
    public function para(?string $nombrePlataforma): PlataformaCatalogo
    {
        $clase = self::PLATAFORMAS[$nombrePlataforma] ?? null;
        if (!$clase) {
            throw new RecursoNoEncontradoException('La plataforma "' . $nombrePlataforma . '" no es compatible');
        }

        return $this->contenedor->make($clase);
    }

    /**
     * Por el parámetro `plataforma` que manda el frontend ("club_bohn", "puntotes"…).
     * Igual que antes: todo lo que no sea club bohn se trata como puntotes.
     */
    public function desdeParametro(?string $plataforma): PlataformaCatalogo
    {
        $normalizado = str_replace('_', ' ', strtolower(trim((string) $plataforma)));

        return $this->para($normalizado === 'club bohn' ? 'club bohn' : 'puntotes');
    }
}
