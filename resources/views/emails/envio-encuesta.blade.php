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
        <h4>¡Hola, {{ $orden->nombre_usuario }}! Confirmamos que tu premio ha sido entregado con éxito. ✅</h4>

        <p>Para asegurarnos de que todo salió perfecto, te invitamos a completar esta encuesta de {{ $orden->encuesta }}:</p>
        <br>

        <a href="{{ $urlval }}"><button>Encuesta</button></a>

        <p>Tu opinión nos ayuda a mejorar y a seguir trayendo los mejores premios para ti. <br>¡Muchas gracias!</p>
    </div>
</body>

</html>