<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>{{ $notification->title }}</title>
    </head>
    <body style="font-family: sans-serif; line-height: 1.5; color: #111827;">
        <h1 style="font-size: 20px;">{{ $notification->title }}</h1>

        @if ($notification->body)
            <p>{{ $notification->body }}</p>
        @endif

        <p>
            <a href="{{ url('/notifications') }}">Buka notifikasi di ENT Inventory</a>
        </p>

        <p style="font-size: 12px; color: #6b7280;">
            Email ini dikirim otomatis. Jangan balas pesan ini.
        </p>
    </body>
</html>
