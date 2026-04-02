<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bienvenido a {{ config('app.name') }}</title>
</head>
<body>
    <p>Hola {{ $user->name }},</p>

    <p>Gracias por registrarte en {{ config('app.name') }}. Tu cuenta ha sido creada correctamente.</p>

    <p>Saludos,<br>{{ config('app.name') }}</p>
</body>
</html>
