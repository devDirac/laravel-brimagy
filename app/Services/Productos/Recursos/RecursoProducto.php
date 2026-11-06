<?php

namespace App\Services\Productos\Recursos;

/**
 * Describe un recurso del producto (color, talla, monto, foto…): dónde se guarda,
 * qué campos tiene y cómo se nombra en mensajes y bitácora.
 * Los servicios usan esta descripción y no conocen ningún recurso en particular.
 */
abstract class RecursoProducto
{
    /** Clave en PlataformaCatalogo::modelosRemotos(), o null si el recurso solo existe en local. */
    abstract public function claveRemota(): ?string;

    /** Modelo local (dc_*). */
    abstract public function modeloLocal(): string;

    /** Campo del request con el id local (ej. id_color). */
    abstract public function campoId(): string;

    /** Columna local con el id del registro remoto; también es el campo del request (ej. id_color_brimagy). */
    public function campoIdRemoto(): ?string
    {
        return null;
    }

    /** Campos que se capturan en el formulario: campo local => columna remota. */
    public function campos(): array
    {
        return [];
    }

    /** Reglas de validación de los campos capturados. */
    public function reglasCampos(): array
    {
        return [];
    }

    /** Campo que no se puede repetir dentro del mismo producto. */
    public function campoUnico(): ?string
    {
        return null;
    }

    /** Campo que describe al registro en la bitácora (ej. el nombre del color). */
    abstract public function campoDescriptivo(): string;

    /** Tabla que se anota en la bitácora. */
    abstract public function tablaBitacora(): string;

    /** Nombre en minúsculas: "color", "talla", "foto"… */
    abstract public function nombre(): string;

    abstract public function esFemenino(): bool;

    /** "de producto" o "de monto", para los eventos de activar/desactivar. */
    public function ambito(): string
    {
        return 'producto';
    }

    // ---------- Textos (mismos que ya se usaban en cada método) ----------

    /** "el color" / "la talla" */
    public function conArticulo(): string
    {
        return ($this->esFemenino() ? 'la ' : 'el ') . $this->nombre();
    }

    /** "un color" / "una talla" */
    public function conIndefinido(): string
    {
        return ($this->esFemenino() ? 'una ' : 'un ') . $this->nombre();
    }

    /** "registrado" / "registrada" */
    public function concordar(string $raiz): string
    {
        return $raiz . ($this->esFemenino() ? 'a' : 'o');
    }

    /** "Color registrado exitosamente.", "Talla editada exitosamente."… */
    public function mensajeGuardado(bool $editado): string
    {
        return ucfirst($this->nombre()) . ' ' . $this->concordar($editado ? 'editad' : 'registrad') . ' exitosamente.';
    }

    /** "Color activado correctamente.", "Foto desactivada correctamente."… */
    public function mensajeEstatus(bool $activo): string
    {
        return ucfirst($this->nombre()) . ' ' . $this->concordar($activo ? 'activad' : 'desactivad') . ' correctamente.';
    }

    /** "Este color no se encuentra" / "Esta talla no se encuentra" */
    public function mensajeNoEncontrado(): string
    {
        return ($this->esFemenino() ? 'Esta ' : 'Este ') . $this->nombre() . ' no se encuentra';
    }

    /** "El color Rojo ya existe" */
    public function mensajeRepetido($valor): string
    {
        return ucfirst($this->conArticulo()) . ' ' . $valor . ' ya existe';
    }

    /** "Error al registrar el color", "Error al activar la talla"… */
    public function mensajeError(string $accion): string
    {
        return "Error al {$accion} " . $this->conArticulo();
    }
}
