<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        h2 { color: #00506E; border-bottom: 2px solid #00506E; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; border: 1px solid #00506E; text-align: left; }
        th { background-color: #00506E; color: #fff; }
        tfoot td { font-weight: bold; background-color: #f5f5f5; color: #00506E; }
        .btn {
            display: inline-block;
            background-color: #00506E;
            color: #fff !important;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 10px;
        }
        .footer { color: #666; font-size: 14px; margin-top: 20px; }
        .header {
            background: linear-gradient(180deg, rgba(0, 181, 225, 1) 0%, rgba(0, 132, 171, 1) 100%);
            padding: 15px 0;
            text-align: center
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="http://201.149.0.141:8081/img/logo.png" alt="" width="150">
        </div>

        <h2 style="text-align:center;color:#00506E">Orden de compra generada</h2>

        <p>Se le ha enviado una orden de compra para su validación. A continuación el detalle de los productos:</p>

        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($productosData->productos as $producto)
                <tr>
                    <td>{{ $producto['sku'] ?? '-' }}</td>
                    <td>{{ $producto['nombre_producto'] }}</td>
                    <td>{{ $producto['cantidad_producto'] }}</td>
                    <td>${{ number_format($producto['precio_unitario'], 2) }}</td>
                    <td>${{ number_format($producto['importe_total'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">IVA</td>
                    <td>${{ number_format($productosData->total_iva, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4">Total General</td>
                    <td>${{ number_format($productosData->total_general, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <p style="text-align:center">
            <a href="{{ $urlval }}" class="btn">Ver orden de compra</a>
        </p>

        <p class="footer">Si el botón no funciona, copie y pegue este enlace en su navegador:<br>
            {{ $urlval }}
        </p>
    </div>
</body>

</html>