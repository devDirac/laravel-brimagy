<?php

namespace App\Services\Plataformas;

use App\Exceptions\RecursoNoEncontradoException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait ReflejaRegistrosRemotos
{
    public function modelosRemotos(string $recurso): array
    {
        $modelos = static::MODELOS[$recurso] ?? null;
        if (!$modelos) {
            throw new \InvalidArgumentException("La plataforma no maneja el recurso \"{$recurso}\"");
        }

        return $modelos;
    }

    public function conexiones(): array
    {
        $conexiones = [];
        foreach (static::MODELOS as $modelos) {
            foreach ($modelos as $clase) {
                $conexiones[] = (new $clase())->getConnectionName();
            }
        }

        return array_values(array_unique(array_filter($conexiones)));
    }

    public function buscarRegistroRemoto(string $recurso, $id): ?Model
    {
        if (!$id) {
            return null;
        }
        $principal = $this->modelosRemotos($recurso)[0];

        return $principal::find($id);
    }

    public function crearRegistroRemoto(string $recurso, array $datos): int
    {
        $modelos = $this->modelosRemotos($recurso);
        $principal = array_shift($modelos);

        $registro = $principal::create($datos);
        foreach ($modelos as $espejo) {
            $this->crearEspejo($espejo, $registro, $datos);
        }

        return $registro->id;
    }

    public function actualizarRegistroRemoto(string $recurso, $id, array $datos): void
    {
        $modelos = $this->modelosRemotos($recurso);
        $principal = array_shift($modelos);

        $registro = $id ? $principal::find($id) : null;
        if (!$registro) {
            throw new RecursoNoEncontradoException('No se encuentra el registro en la plataforma');
        }

        $registro->update($datos);

        foreach ($modelos as $espejo) {
            $copia = $this->buscarEspejo($espejo, $registro);
            if ($copia) {
                $copia->update($datos);
            }
        }
    }

    private function crearEspejo(string $clase, Model $principal, array $datos): void
    {
        if (!$clase::whereKey($principal->getKey())->exists()) {
            $copia = new $clase();
            $copia->forceFill($datos + [$copia->getKeyName() => $principal->getKey()])->save();

            return;
        }

        Log::warning('Espejo desincronizado: el id ya está ocupado, se crea con otro id', [
            'modelo' => $clase,
            'id' => $principal->getKey(),
        ]);
        $clase::create($datos);
    }

    private function buscarEspejo(string $clase, Model $principal): ?Model
    {
        $copia = $clase::find($principal->getKey());
        if (!$copia) {
            return null;
        }

        $premioPrincipal = $principal->getAttribute('award_id');
        $premioCopia = $copia->getAttribute('award_id');
        if ($premioPrincipal !== null && $premioCopia !== null && (string) $premioPrincipal !== (string) $premioCopia) {
            Log::warning('Espejo desincronizado: el id corresponde a otro premio, no se actualiza', [
                'modelo' => $clase,
                'id' => $principal->getKey(),
            ]);

            return null;
        }

        return $copia;
    }
}
