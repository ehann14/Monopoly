<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu — Monopoly Web Game</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#0f3d2e; margin:0; min-height:100vh; padding:16px; color:#1d2b24; }
        .wrap { max-width:520px; margin:0 auto; }
        .card { background:#fff; border-radius:16px; padding:20px; margin-bottom:14px; }
        h1 { color:#fff; margin:8px 0 16px; font-size:1.5rem; }
        h2 { margin:0 0 10px; font-size:1.1rem; }
        label { display:block; margin:10px 0 4px; font-size:.9rem; }
        input[type=text] { width:100%; padding:.65rem; border:1px solid #bbb; border-radius:8px; box-sizing:border-box; }
        button.primary { margin-top:14px; width:100%; padding:.8rem; border:0; border-radius:10px; background:#f2c300; color:#222; font-weight:700; font-size:1rem; cursor:pointer; }
        .err { color:#b00020; font-size:.85rem; margin-top:4px; }
        .note { font-size:.85rem; color:#556; margin-top:10px; }
        ul { padding-left:18px; margin:0; } li { margin:6px 0; }
        a { color:#0f3d2e; font-weight:600; }
        .logout { background:none; border:0; color:#f2c300; cursor:pointer; font-size:.9rem; padding:0; }
        .bar { display:flex; justify-content:space-between; align-items:center; }
        .bar span { color:#fff; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="bar">
        <span>Halo, {{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Keluar</button></form>
    </div>
    <h1>Monopoly Web Game</h1>

    <div class="card">
        <h2>Permainan baru (lokal, 2–4 pemain)</h2>
        <form method="POST" action="{{ route('game.store') }}">
            @csrf
            @foreach ([0, 1, 2, 3] as $i)
                <label for="p{{ $i }}">Pemain {{ $i + 1 }}{{ $i >= 2 ? ' (opsional)' : '' }}</label>
                <input id="p{{ $i }}" type="text" name="players[]" maxlength="20"
                       value="{{ old('players.' . $i, $i === 0 ? auth()->user()->name : '') }}">
            @endforeach
            @error('players') <div class="err">{{ $message }}</div> @enderror
            @foreach ($errors->get('players.*') as $messages)
                @foreach ($messages as $message) <div class="err">{{ $message }}</div> @endforeach
            @endforeach
            <button class="primary" type="submit">Mulai permainan</button>
        </form>
        <p class="note">Semua pemain bergantian di perangkat ini. Mode lawan komputer dan online tersedia di tahap berikutnya.</p>
    </div>

    @if ($rooms->isNotEmpty())
        <div class="card">
            <h2>Lanjutkan permainan</h2>
            <ul>
                @foreach ($rooms as $r)
                    <li><a href="{{ route('game.show', $r->code) }}">Room {{ $r->code }}</a>
                        <small>({{ $r->created_at->diffForHumans() }})</small></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
</body>
</html>