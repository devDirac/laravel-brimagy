<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FolioCanjeResolver
{
    public function enriquecer(Collection $canjes, Collection $plataformas): Collection
    {
        $mapa = collect(config('plataformas.conexiones_canjes', []))
            ->mapWithKeys(fn($conexion, $nombre) => [$this->normalizar($nombre) => $conexion]);

        $datos = []; // [id_plataforma => Collection keyBy id_canje]

        foreach ($canjes->groupBy('id_plataforma') as $idPlataforma => $grupo) {
            $conexion = $mapa->get($this->normalizar($plataformas->get($idPlataforma)));
            $ids = $grupo->pluck('id_canje')->filter()->unique()->values();

            if (!$conexion || $ids->isEmpty()) {
                continue;
            }

            try {
                $datos[$idPlataforma] = DB::connection($conexion)
                    ->table('swaps as s')
                    ->leftJoin('users as u', 'u.id', '=', 's.user_id')
                    ->leftJoin('address as ad', 'ad.id', '=', 's.address_id')
                    ->whereIn('s.id', $ids)
                    ->get([
                        's.id',
                        's.folio',
                        'u.name',
                        'u.first_last_name',
                        'u.second_last_name',
                        'u.email',
                        'u.phone',
                        'ad.street',
                        'ad.number',
                        'ad.inside',
                        'ad.colony',
                        'ad.municipality',
                        'ad.state',
                        'ad.postal_code',
                        'ad.between_1',
                        'ad.between_2',
                        'ad.additional_reference',
                    ])
                    ->keyBy('id');
            } catch (\Throwable $e) {
                Log::warning("FolioCanjeResolver ({$conexion}): " . $e->getMessage());
            }
        }

        return $canjes->map(function ($c) use ($plataformas, $datos) {
            $s = isset($datos[$c->id_plataforma])
                ? $datos[$c->id_plataforma]->get($c->id_canje)
                : null;

            $c->plataforma = $plataformas->get($c->id_plataforma);
            $c->folio = $s->folio ?? null;
            $c->nombre_usuario = $s ? $this->nombreCompleto($s) : null;
            $c->email = $s->email ?? null;
            $c->phone = $s->phone ?? null;
            $c->calle = $s->street ?? null;
            $c->numero_calle = $s->number ?? null;
            $c->numero_interior = $s->inside ?? null;
            $c->colonia = $s->colony ?? null;
            $c->municipio = $s->municipality ?? null;
            $c->estado = $s->state ?? null;
            $c->codigo_postal = $s->postal_code ?? null;
            $c->between_1 = $s->between_1 ?? null;
            $c->between_2 = $s->between_2 ?? null;
            $c->referencia_adicional = $s->additional_reference ?? null;

            return $c;
        });
    }

    private function nombreCompleto(object $s): ?string
    {
        $nombre = trim(implode(' ', array_filter([
            $s->name ?? null,
            $s->first_last_name ?? null,
            $s->second_last_name ?? null,
        ])));

        return $nombre !== '' ? $nombre : null;
    }

    private function normalizar(?string $nombre): string
    {
        return str_replace('_', ' ', mb_strtolower(trim($nombre ?? '')));
    }
}