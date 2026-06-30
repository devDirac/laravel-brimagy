<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        h2 {
            color: #00506E;
            border-bottom: 2px solid #00506E;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        td {
            padding: 4px;
            border: 1px solid #00506E;
        }

        .label {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 40%;
            color: #00506E;
        }

        .codigo {
            font-size: 18px;
            font-weight: bold;
            color: #00506E;
            text-align: center;
            padding: 10px;
        }

        .codigo b {
            font-size: 24px;
            color: #a5eb2f;
        }

        .footer {
            color: #666;
            font-size: 14px;
            margin-top: 20px;
        }

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
        <h2 style="text-align:center;color:#00506E">Nueva solicitud de validación de identidad</h2>

        <p>Se ha generado una nueva solicitud de validación para el siguiente canje:</p>

        <table>
            <tr>
                <td class="label">Folio:</td>
                <td>{{ $canje->folio }}</td>
            </tr>
            <tr>
                <td class="label">Cliente:</td>
                <td>{{ $canje->nombre_usuario }}</td>
            </tr>
            <tr>
                <td class="label">Correo:</td>
                <td>{{ $canje->email }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono:</td>
                <td>{{ $canje->phone }}</td>
            </tr>
            <tr>
                <td class="label">Premio:</td>
                <td>{{ $canje->nombre_premio }}</td>
            </tr>
            <tr>
                <td class="label">Puntos canjeados:</td>
                <td>{{ $canje->puntos_canjeados }}</td>
            </tr>
            <tr>
                <td class="label">Dirección:</td>
                <td>
                    {{ $canje->calle }} {{ $canje->numero_calle }},
                    {{ $canje->colonia }},
                    {{ $canje->municipio }},
                    CP: {{ $canje->codigo_postal }}
                </td>
            </tr>
        </table>

        <p>Ingresa a la web <a href="{{ $urlval }}">Brimagy</a> y solicita tu código para verificar tu identidad</p>
    </div>
</body>

</html>