<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña - CEFIRET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a2980, #26d0ce);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 14px;
            padding: 36px;
            box-shadow: 0 18px 50px rgba(0,0,0,0.18);
        }
        .login-box h2 {
            margin-bottom: 24px;
            font-weight: 700;
            color: #163d72;
        }
        .btn-login {
            background: #163d72;
            color: white;
            width: 100%;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #0f3058;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>¿Olvidaste tu contraseña?</h2>

        @if(session('sessionRecuperarContrasennia') && session('sessionRecuperarContrasennia') === 'true')
            <div class="alert alert-success">{{ session('mensaje') }}</div>
        @endif
        @if(session('sessionRecuperarContrasennia') === 'false')
            <div class="alert alert-danger">{{ session('mensaje') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="correo" class="form-control" value="{{ old('correo') }}" required>
                @error('correo')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-login">Solicitar contraseña</button>
        </form>

        <p class="mt-4 text-center">
            <a href="{{ route('login.form') }}">Volver a iniciar sesión</a>
        </p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
