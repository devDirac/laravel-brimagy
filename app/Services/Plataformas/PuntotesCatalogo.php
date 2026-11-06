<?php

namespace App\Services\Plataformas;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\CatalogoCategoria;
use App\Models\ColoresBrimagy;
use App\Models\ColoresLocal;
use App\Models\FotosOfertasBrimagy;
use App\Models\FotosOfertasLocal;
use App\Models\FotosProductoBrimagy;
use App\Models\FotosProductoLocal;
use App\Models\MontosBrimagy;
use App\Models\MontosLocal;
use App\Models\ProductoBrimagy;
use App\Models\ProductoLocal;
use App\Models\SubCategoria;
use App\Models\TallasBrimagy;
use App\Models\TallasLocal;
use Illuminate\Database\Eloquent\Model;

/**
 * Puntotes guarda todo dos veces: en la BD de Brimagy (principal) y en su copia local (espejo).
 */
class PuntotesCatalogo implements PlataformaCatalogo
{
    use ReflejaRegistrosRemotos;

    /** recurso => [modelo principal, espejos...] */
    const MODELOS = [
        'productos' => [ProductoBrimagy::class, ProductoLocal::class],
        self::COLORES => [ColoresBrimagy::class, ColoresLocal::class],
        self::TALLAS => [TallasBrimagy::class, TallasLocal::class],
        self::MONTOS => [MontosBrimagy::class, MontosLocal::class],
        self::FOTOS => [FotosProductoBrimagy::class, FotosProductoLocal::class],
        self::FOTOS_PROMO => [FotosOfertasBrimagy::class, FotosOfertasLocal::class],
    ];

    public function valoresPorDefecto(): array
    {
        return [
            'fee_brimagy' => 12,
            'envio_base' => 180,
            'costo_caja' => 19,
            'envio_extra' => 0,
            'valor_factor' => 15,
        ];
    }

    public function buscarSubcategoriaPorNombre(?string $descripcion): ?Model
    {
        return SubCategoria::where('desc', $descripcion)->first();
    }

    public function carpetaFtp($idSubcategoria): ?string
    {
        $subcategoria = SubCategoria::find($idSubcategoria);
        $categoria = $subcategoria ? CatalogoCategoria::find($subcategoria->category_id) : null;

        return $categoria ? $categoria->file_path : null;
    }

    public function buscarProductoRemoto($idRemoto): ?Model
    {
        return $idRemoto ? ProductoBrimagy::find($idRemoto) : null;
    }

    public function crearProductoRemotoSiNoExiste($idRemoto, array $datos): int
    {
        $brimagy = $this->buscarProductoRemoto($idRemoto);
        if (!$brimagy) {
            $brimagy = ProductoBrimagy::create($datos);
        }

        // La copia local se busca con el mismo id que en Brimagy
        if (!ProductoLocal::whereKey($brimagy->id)->exists()) {
            $this->crearEspejo(ProductoLocal::class, $brimagy, $datos);
        }

        return $brimagy->id;
    }

    public function actualizarProductoRemoto($idRemoto, array $datos): void
    {
        $brimagy = $this->buscarProductoRemoto($idRemoto);
        if (!$brimagy) {
            throw new RecursoNoEncontradoException('No se encuentra el producto brimagy');
        }

        $brimagy->update($datos);

        $local = ProductoLocal::find($idRemoto);
        if ($local) {
            $local->update($datos);
        }
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
