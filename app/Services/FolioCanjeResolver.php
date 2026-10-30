<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FolioCanjeResolver
{
    public function enriquecer(Collection $canjes, Collection $plataformas): Collection
    {
        $mapa = collect(config('plataformas.conexiones_canjes', []))
            ->mapWithKeys(fn($conexion, $nombre) => [$this->normalizar($nombre) => $conexion]);

        $folios = [];

        foreach ($canjes->groupBy('id_plataforma') as $idPlataforma => $grupo) {
            $nombre   = $plataformas->get($idPlataforma);
            $conexion = $mapa->get($this->normalizar($nombre));
            $ids      = $grupo->pluck('id_canje')->unique()->values();

            if (!$conexion) {
                continue;
            }

            $folios[$idPlataforma] = DB::connection($conexion)
                ->table('swaps')
                ->whereIn('id', $ids)
                ->pluck('folio', 'id');
        }

        return $canjes->map(function ($c) use ($plataformas, $folios) {
            $c->plataforma = $plataformas->get($c->id_plataforma);
            $c->folio = isset($folios[$c->id_plataforma])
                ? $folios[$c->id_plataforma]->get($c->id_canje)
                : null;
            return $c;
        });
    }

    private function normalizar(?string $nombre): string
    {
        return str_replace('_', ' ', mb_strtolower(trim($nombre ?? '')));
    }
}
