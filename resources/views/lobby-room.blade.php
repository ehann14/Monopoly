<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $room->name ?? 'Room ' . $room->code }} — Monopoly Web Game</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#0f3d2e; margin:0; min-height:100vh; padding:16px; color:#1d2b24; }
        .wrap { max-width:700px; margin:0 auto; }
        .card { background:#fff; border-radius:16px; padding:20px; margin-bottom:14px; }
        h1 { color:#fff; margin:8px 0 16px; font-size:1.5rem; }
        h2 { margin:0 0 10px; font-size:1.1rem; }
        button.primary { padding:.8rem 1.5rem; border:0; border-radius:10px; background:#f2c300; color:#222; font-weight:700; font-size:1rem; cursor:pointer; }
        button.danger { padding:.8rem 1.5rem; border:0; border-radius:10px; background:#dc3545; color:#fff; font-weight:700; font-size:1rem; cursor:pointer; }
        button.secondary { padding:.6rem 1.2rem; border:0; border-radius:8px; background:#ddd; color:#222; font-weight:600; font-size:.9rem; cursor:pointer; }
        .logout { background:none; border:0; color:#f2c300; cursor:pointer; font-size:.9rem; padding:0; }
        .bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .bar span { color:#fff; }
        .nav-links { display:flex; gap:12px; margin-bottom:16px; }
        .nav-links a { color:#fff; padding:8px 16px; background:rgba(255,255,255,0.1); border-radius:8px; text-decoration:none; }
        .nav-links a:hover { background:rgba(255,255,255,0.2); }
        
        /* Player list */
        .player-list { display:grid; gap:12px; }
        .player-item { background:#f8f9fa; border-radius:12px; padding:16px; display:flex; justify-content:space-between; align-items:center; }
        .player-info { display:flex; align-items:center; gap:12px; }
        .player-token { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:1.2rem; }
        .player-details h3 { margin:0 0 4px; font-size:1rem; }
        .player-details p { margin:0; font-size:.85rem; color:#666; }
        .ready-badge { padding:4px 10px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .ready-yes { background:#d4edda; color:#155724; }
        .ready-no { background:#f8d7da; color:#721c24; }
        .host-badge { background:#cce5ff; color:#004085; padding:2px 8px; border-radius:12px; font-size:.75rem; margin-left:8px; }
        
        .room-code { background:#f2c300; padding:12px; border-radius:8px; text-align:center; margin-bottom:16px; }
        .room-code strong { font-size:1.5rem; letter-spacing:2px; }
        
        .actions { display:flex; gap:12px; margin-top:16px; flex-wrap:wrap; }
        .copy-btn { background:none; border:1px solid #0f3d2e; padding:4px 12px; border-radius:6px; cursor:pointer; font-size:.85rem; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .waiting-text { animation: pulse 2s infinite; color:#666; font-size:.9rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="bar">
        <span>Halo, {{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Keluar</button></form>
    </div>
    
    <h1>{{ $room->name ?? 'Room ' . $room->code }}</h1>
    
    <div class="nav-links">
        <a href="{{ route('lobby.index') }}">← Kembali ke Lobby</a>
    </div>

    <div class="card">
        <div class="room-code">
            <p style="margin:0 0 4px;font-size:.9rem;">Kode Room:</p>
            <strong id="roomCode">{{ $room->code }}</strong>
            <button class="copy-btn" onclick="copyCode()">📋 Salin</button>
        </div>
        
        <h2>Pemain ({{ $players->count() }}/{{ $room->max_players }})</h2>
        
        <div class="player-list">
            @foreach($players as $player)
                <div class="player-item">
                    <div class="player-info">
                        <div class="player-token" style="background:{{ $player->token }};">
                            {{ strtoupper(substr($player->name, 0, 1)) }}
                        </div>
                        <div class="player-details">
                            <h3>
                                {{ $player->name }}
                                @if($player->user_id == $room->host_user_id)
                                    <span class="host-badge">HOST</span>
                                @endif
                            </h3>
                            <p>Seat {{ $player->seat + 1 }}</p>
                        </div>
                    </div>
                    <span class="ready-badge {{ $player->is_ready ? 'ready-yes' : 'ready-no' }}">
                        {{ $player->is_ready ? '✓ Siap' : '✗ Belum Siap' }}
                    </span>
                </div>
            @endforeach
            
            @for($i = $players->count(); $i < $room->max_players; $i++)
                <div class="player-item" style="opacity:0.5;">
                    <div class="player-info">
                        <div class="player-token" style="background:#ccc;">?</div>
                        <div class="player-details">
                            <h3>Menunggu pemain...</h3>
                            <p>Seat {{ $i + 1 }}</p>
                        </div>
                    </div>
                </div>
            @endfor
        </div>
        
        @if($currentUserPlayer)
            <div class="actions">
                <form method="POST" action="{{ route('lobby.ready', $room->code) }}">
                    @csrf
                    <button class="primary" type="submit">
                        {{ $currentUserPlayer->is_ready ? 'Batal Siap' : 'Saya Siap!' }}
                    </button>
                </form>
                
                <form method="POST" action="{{ route('lobby.leave', $room->code) }}">
                    @csrf
                    <button class="danger" type="submit" onclick="return confirm('Yakin ingin keluar dari room?')">
                        Keluar Room
                    </button>
                </form>
                
                @if($canStart)
                    <form method="POST" action="{{ route('lobby.start', $room->code) }}">
                        @csrf
                        <button class="primary" type="submit" style="background:#28a745;">
                            🎮 Mulai Permainan
                        </button>
                    </form>
                @elseif($room->host_user_id == auth()->user()->id)
                    <p class="waiting-text">Menunggu minimal 2 pemain siap...</p>
                @endif
            </div>
        @endif
        
        <p class="note" style="margin-top:16px;">
            Bagikan kode room ke teman Anda. Minimal 2 pemain harus siap sebelum host dapat memulai permainan.
        </p>
    </div>
</div>

<script>
function copyCode() {
    const code = document.getElementById('roomCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        alert('Kode room disalin: ' + code);
    }).catch(() => {
        prompt('Salin kode room:', code);
    });
}

// Auto-refresh setiap 5 detik untuk update status pemain
setInterval(() => {
    location.reload();
}, 5000);
</script>
</body>
</html>