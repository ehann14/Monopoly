<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Menu</title></head>
<body style="font-family:system-ui;padding:2rem">
    <h1>Halo, {{ auth()->user()->name }}</h1>
    <p>Menu utama akan dibangun di Tahap 2.</p>
    <form method="POST" action="/logout">@csrf<button>Keluar</button></form>
</body></html>