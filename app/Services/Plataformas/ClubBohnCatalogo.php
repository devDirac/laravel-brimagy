<?php

namespace App\Services\Plataformas;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\CategoriaClub;
use App\Models\ColoresClub;
use App\Models\FotosOfertasClub;
use App\Models\FotosProductoClub;
use App\Models\MontosClub;
use App\Models\ProductoClub;
use App\Models\SubCategoriaClub;
use App\Models\TallasClub;
use Illuminate\Database\Eloquent\Model;

/** Club Bohn guarda una sola copia, en su propia BD. */
class ClubBohnCatalogo implements PlataformaCatalogo
{
    use ReflejaRegistrosRemotos;

    /** recurso => [modelo principal] */
    const MODELOS = [
        'productos' => [ProductoClub::class],
        self::COLORES => [ColoresClub::class],
        self::TALLAS => [TallasClub::class],
        self::MONTOS => [MontosClub::class],
        self::FOTOS => [FotosProductoClub::class],
        self::FOTOS_PROMO => [FotosOfertasClub::class],
    ];

    public function valoresPorDefecto(): array
    {
        return [
            'fee_brimagy' => 15,
            'envio_base' => 180,
            'costo_caja' => 19,
            'envio_extra' => 0,
            'valor_factor' => 15,
        ];
    }

    public function buscarSubcategoriaPorNombre(?string $descripcion): ?Model
    {
        return SubCategoriaClub::where('desc', $descripcion)->first();
    }

    public function carpetaFtp($idSubcategoria): ?string
    {
        $subcategoria = SubCategoriaClub::find($idSubcategoria);
        $categoria = $subcategoria ? CategoriaClub::find($subcategoria->category_id) : null;

        return $categoria ? $categoria->file_path : null;
    }

    public function buscarProductoRemoto($idRemoto): ?Model
    {
        return $idRemoto ? ProductoClub::find($idRemoto) : null;
    }

    public function crearProductoRemotoSiNoExiste($idRemoto, array $datos): int
    {
        $producto = $this->buscarProductoRemoto($idRemoto) ?? ProductoClub::create($datos);

        return $producto->id;
    }

    public function actualizarProductoRemoto($idRemoto, array $datos): void
    {
        $producto = $this->buscarProductoRemoto($idRemoto);
        if (!$producto) {
            throw new RecursoNoEncontradoException('No se encuentra el producto brimagy');
        }

        $producto->update($datos);
    }

    public function agregarColor(int $idRemoto, string $color): int
    {
        return $this->crearRegistroRemoto(self::COLORES, [
            'award_id' => $idRemoto,
            'color' => $color,
            'status' => 'ACTIVE',
        ]);
    }

    public function agregarTalla(int $idRemoto, string $talla): int
    {
        return $this->crearRegistroRemoto(self::TALLAS, [
            'award_id' => $idRemoto,
            'size' => $talla,
            'status' => 'ACTIVE',
        ]);
    }
}
