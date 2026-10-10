<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monopoly Web Game</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#0f3d2e; color:#fff; margin:0; min-height:100vh; display:grid; place-items:center; text-align:center; }
        .wrap { padding:1.5rem; max-width:520px; }
        h1 { font-size:2.2rem; margin:0 0 .5rem; }
        p { opacity:.85; margin:0 0 1.5rem; }
        .btns { display:flex; gap:.75rem; justify-content:center; flex-wrap:wrap; }
        a.btn { padding:.75rem 1.5rem; border-radius:10px; text-decoration:none; font-weight:600; }
        a.primary { background:#f2c300; color:#222; }
        a.ghost { border:1px solid rgba(255,255,255,.6); color:#fff; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Monopoly Web Game</h1>
    <p>Main melawan komputer atau bersama teman secara online.</p>
    <div class="btns">
        @auth
            <a class="btn primary" href="{{ route('menu') }}">Ke menu</a>
        @else
            <a class="btn primary" href="{{ route('login') }}">Masuk</a>
            <a class="btn ghost" href="{{ route('register') }}">Daftar</a>
        @endauth
    </div>
</div>
</body>
</html>