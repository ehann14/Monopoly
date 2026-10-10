<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lobby Multiplayer — Monopoly Web Game</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#0f3d2e; margin:0; min-height:100vh; padding:16px; color:#1d2b24; }
        .wrap { max-width:900px; margin:0 auto; }
        .card { background:#fff; border-radius:16px; padding:20px; margin-bottom:14px; }
        h1 { color:#fff; margin:8px 0 16px; font-size:1.5rem; }
        h2 { margin:0 0 10px; font-size:1.1rem; }
        label { display:block; margin:10px 0 4px; font-size:.9rem; }
        input[type=text], select { width:100%; padding:.65rem; border:1px solid #bbb; border-radius:8px; box-sizing:border-box; font-size:1rem; }
        button.primary { margin-top:14px; padding:.8rem 1.5rem; border:0; border-radius:10px; background:#f2c300; color:#222; font-weight:700; font-size:1rem; cursor:pointer; }
        button.secondary { padding:.6rem 1.2rem; border:0; border-radius:8px; background:#ddd; color:#222; font-weight:600; font-size:.9rem; cursor:pointer; }
        .err { color:#b00020; font-size:.85rem; margin-top:4px; }
        .note { font-size:.85rem; color:#556; margin-top:10px; }
        a { color:#0f3d2e; font-weight:600; text-decoration:none; }
        a:hover { text-decoration:underline; }
        .logout { background:none; border:0; color:#f2c300; cursor:pointer; font-size:.9rem; padding:0; }
        .bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .bar span { color:#fff; }
        .nav-links { display:flex; gap:12px; margin-bottom:16px; }
        .nav-links a { color:#fff; padding:8px 16px; background:rgba(255,255,255,0.1); border-radius:8px; }
        .nav-links a.active { background:#f2c300; color:#222; }
        
        /* Room list */
        .room-list { display:grid; gap:12px; }
        .room-item { background:#f8f9fa; border-radius:12px; padding:16px; display:flex; justify-content:space-between; align-items:center; }
        .room-info h3 { margin:0 0 4px; font-size:1rem; }
        .room-info p { margin:0; font-size:.85rem; color:#666; }
        .room-meta { display:flex; gap:16px; align-items:center; }
        .badge { padding:4px 10px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .badge-waiting { background:#d4edda; color:#155724; }
        .badge-playing { background:#fff3cd; color:#856404; }
        .player-count { font-size:.9rem; color:#666; }
        
        /* Create form */
        .form-row { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .checkbox-label { display:flex; align-items:center; gap:8px; margin:10px 0; cursor:pointer; }
        .checkbox-label input { width:auto; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="bar">
        <span>Halo, {{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Keluar</button></form>
    </div>
    
    <h1>Lobby Multiplayer</h1>
    
    <div class="nav-links">
        <a href="{{ route('menu') }}">← Menu Utama</a>
        <a href="{{ route('lobby.index') }}" class="active">Daftar Room</a>
    </div>

    @if(session('error'))
        <div class="card" style="background:#ffebee;">
            <p class="err">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Buat Room Baru -->
    <div class="card">
        <h2>Buat Room Baru</h2>
        <form method="POST" action="{{ route('lobby.create') }}">
            @csrf
            <label for="roomName">Nama Room (opsional)</label>
            <input id="roomName" type="text" name="name" maxlength="100" placeholder="Contoh: Room Seru Ehan">
            
            <div class="form-row">
                <div>
                    <label for="maxPlayers">Maksimal Pemain</label>
                    <select id="maxPlayers" name="max_players">
                        <option value="2">2 Pemain</option>
                        <option value="3">3 Pemain</option>
                        <option value="4" selected>4 Pemain</option>
                    </select>
                </div>
                <div>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_public" value="1" checked>
                        <span>Room Publik (terlihat di daftar)</span>
                    </label>
                </div>
            </div>
            
            <button class="primary" type="submit">Buat Room</button>
        </form>
    </div>

    <!-- Gabung via Kode -->
    <div class="card">
        <h2>Gabung dengan Kode</h2>
        <form method="POST" action="{{ route('lobby.join', ['code' => '__CODE__']) }}" id="joinForm">
            @csrf
            <label for="roomCode">Kode Room (6 karakter)</label>
            <input id="roomCode" type="text" name="_code" maxlength="6" placeholder="Contoh: ABC123" required pattern="[A-Z0-9]{6}">
            <button class="primary" type="submit">Gabung Room</button>
        </form>
        <p class="note">Masukkan kode room yang diberikan oleh host.</p>
    </div>

    <script>
        document.getElementById('joinForm').addEventListener('submit', function(e) {
            const code = document.getElementById('roomCode').value.toUpperCase();
            this.action = this.action.replace('__CODE__', code);
        });
    </script>

    <!-- Room Saya -->
    @if($userRooms->isNotEmpty())
        <div class="card">
            <h2>Room Saya</h2>
            <div class="room-list">
                @foreach($userRooms as $room)
                    <div class="room-item">
                        <div class="room-info">
                            <h3>{{ $room->name ?? 'Room ' . $room->code }}</h3>
                            <p>Kode: <strong>{{ $room->code }}</strong> | Dibuat {{ $room->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="room-meta">
                            <span class="badge badge-{{ $room->status }}">{{ ucfirst($room->status) }}</span>
                            <a href="{{ route('lobby.show', $room->code) }}" class="secondary">Lihat</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Daftar Room Publik -->
    <div class="card">
        <h2>Room Tersedia</h2>
        @if($rooms->isEmpty())
            <p class="note">Belum ada room yang tersedia. Buat room baru atau tunggu teman Anda membuat room!</p>
        @else
            <div class="room-list">
                @foreach($rooms as $room)
                    <div class="room-item">
                        <div class="room-info">
                            <h3>{{ $room->name ?? 'Room ' . $room->code }}</h3>
                            <p>Host: {{ $room->host->name }} | Kode: <strong>{{ $room->code }}</strong></p>
                            <p class="player-count">{{ $room->players->count() }}/{{ $room->max_players }} pemain</p>
                        </div>
                        <div class="room-meta">
                            <span class="badge badge-waiting">Menunggu</span>
                            <form method="POST" action="{{ route('lobby.join', $room->code) }}">
                                @csrf
                                <button class="secondary" type="submit">Gabung</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div style="margin-top:16px;">
                {{ $rooms->links() }}
            </div>
        @endif
    </div>
</div>
</body>
</html>