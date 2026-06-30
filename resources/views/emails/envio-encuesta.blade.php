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
        <h4 style="text-align:center;color:#00506E">¡Hola, {{ $orden->nombre_usuario }}! Confirmamos que tu premio ha sido entregado con éxito. ✅</h4>

        <p>Para asegurarnos de que todo salió perfecto, te invitamos a completar esta encuesta de {{ $orden->encuesta }}:</p>
        <br>

        <a href="{{ $urlval }}"><button>Encuesta</button></a>

        <p>Tu opinión nos ayuda a mejorar y a seguir trayendo los mejores premios para ti. <br>¡Muchas gracias!</p>
    </div>
</body>

</html>