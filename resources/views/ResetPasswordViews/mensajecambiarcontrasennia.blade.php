<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .button {
            background-color: #007bff;
            color: #ffffff;
            padding: 14px 25px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 5px;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Recuperación de Contraseña</h2>
    <p>Estimado/a {{ $nombreCompleto }},</p>
    <p>Hemos recibido una solicitud para restablecer la contraseña de su cuenta. Para proceder con el cambio de contraseña, haga clic en el siguiente botón:</p>
    <a href="{{ config('app.url') }}/password/reset/{{ $token }}" class="button">Restablecer Contraseña</a>
    <p>Si el botón no funciona, copie y pegue el siguiente enlace en su navegador:</p>
    <p><a href="{{ config('app.url') }}/password/reset/{{ $token }}">{{ config('app.url') }}/password/reset/{{ $token }}</a></p>
    <p>Si no solicitó este cambio, ignore este correo. Su contraseña actual permanecerá igual.</p>
    <p>Saludos cordiales,<br>El equipo de CEFIRET</p>
</div>
</body>
</html>
