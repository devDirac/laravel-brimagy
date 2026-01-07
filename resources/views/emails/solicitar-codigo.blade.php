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
            color: #2c3e50;
            border-bottom: 2px solid #eb2fa5;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        td {
            padding: 4px;
            border: 1px solid #eb2fa5;
        }

        .label {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 40%;
            color: #eb2fa5;
        }

        .codigo {
            font-size: 18px;
            font-weight: bold;
            color: #eb2fa5;
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
    </style>
</head>

<body>
    <div class="container">
        <h2>Código de verificación de identidad</h2>

        <p>Ha solicitado un código para verificar su identidad, ingresa a la web y coloca el siguiente código para verificarte:</p>

        <div class="codigo">
            Código de validación:<br> <b>{{ $codigo }}</b>
        </div>

        <p>Link hacia la web de <a href="{{ $urlval }}">Verificación de Identidad</a></p>
    </div>
</body>

</html>