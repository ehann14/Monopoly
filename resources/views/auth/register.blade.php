<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar — Monopoly Web Game</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#0f3d2e; display:grid; place-items:center; min-height:100vh; margin:0; }
        .card { background:#fff; padding:2rem; border-radius:16px; width:min(92vw,380px); box-shadow:0 10px 30px rgba(0,0,0,.3); }
        h1 { margin:0 0 1rem; font-size:1.4rem; }
        label { display:block; margin:.75rem 0 .25rem; font-size:.9rem; }
        input { width:100%; padding:.65rem; border:1px solid #bbb; border-radius:8px; box-sizing:border-box; }
        button { margin-top:1rem; width:100%; padding:.7rem; border:0; border-radius:8px; background:#0f3d2e; color:#fff; font-weight:600; cursor:pointer; }
        .err { color:#b00020; font-size:.85rem; margin-top:.25rem; }
        .alt { margin-top:1rem; font-size:.9rem; text-align:center; }
        .alt a { color:#0f3d2e; font-weight:600; }
    </style>
</head>
<body>
<div class="card">
    <h1>Buat akun</h1>
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <label for="name">Nama tampilan</label>
        <input id="name" name="name" value="{{ old('name') }}" required autofocus>
        @error('name') <div class="err">{{ $message }}</div> @enderror

        <label for="username">Username</label>
        <input id="username" name="username" value="{{ old('username') }}" required>
        @error('username') <div class="err">{{ $message }}</div> @enderror

        <label for="password">Password (min. 8 karakter)</label>
        <input id="password" type="password" name="password" required>
        @error('password') <div class="err">{{ $message }}</div> @enderror

        <label for="password_confirmation">Ulangi password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <button type="submit">Daftar</button>
    </form>
    <div class="alt">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></div>
</div>
</body>
</html>