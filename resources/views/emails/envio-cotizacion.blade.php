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
        <h2>Nueva cotización recibda #000</h2>

        <p>Ha recibido una nueva cotización para su aprobación en la página de Brimagy,
            favor de acceder a través de la siguiente web:</p>
        <br>

        <a href="{{ $urlval }}">Cotización</a>

        <p>Accede ahora y valida los datos de la cotización</p>
    </div>
</body>

</html>