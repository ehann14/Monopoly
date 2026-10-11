<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ $room->code }} — Monopoly Web Game</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --board-bg: #cde6d0;
            --center-bg: #fafafa;
            --tile-border: #333;
            --primary: #0f3d2e;
            --accent: #f2c300;
            --danger: #dc3545;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--primary);
            margin: 0;
            height: 100vh;
            overflow: hidden;
            color: #333;
        }

        #app { display: flex; flex-direction: column; height: 100%; }

        header {
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .game-area { flex: 1; display: flex; position: relative; overflow: hidden; background: #2c3e50; }

        .board-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow: auto;
        }

        .board {
            width: 95vmin;
            height: 95vmin;
            background: var(--board-bg);
            border: 2px solid #000;
            display: grid;
            grid-template-columns: repeat(11, 1fr);
            grid-template-rows: repeat(11, 1fr);
            gap: 1px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            position: relative;
        }

        .tile {
            background: #fff;
            border: 1px solid var(--tile-border);
            position: relative;
            display: flex;
            flex-direction: column;
            font-size: 0.6rem;
            text-align: center;
            overflow: hidden;
        }

        .tile-header { height: 25%; width: 100%; border-bottom: 1px solid #000; }
        .tile-name { padding: 2px; flex: 1; display: flex; align-items: center; justify-content: center; word-break: break-word; font-weight: 600; }
        .tile-price { padding-bottom: 2px; font-size: 0.55rem; }
        .tile.corner { justify-content: center; align-items: center; font-weight: bold; font-size: 0.8rem; background: #e9ecef; }

        .board-center {
            grid-column: 2 / 11;
            grid-row: 2 / 11;
            background: var(--center-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 20px;
            text-align: center;
        }

        .logo-text {
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary);
            transform: rotate(-45deg);
            opacity: 0.1;
            position: absolute;
            pointer-events: none;
        }

        .token {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.4);
            position: absolute;
            transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 50;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 10px;
            color: #fff;
            font-weight: bold;
        }

        .sidebar {
            width: 320px;
            background: #fff;
            border-left: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            z-index: 50;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        }

        .player-list { flex: 1; overflow-y: auto; padding: 15px; }
        .player-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid transparent;
            transition: all 0.2s;
        }
        .player-card.active { background: #fffbe6; border-left-color: var(--accent); box-shadow: 0 2px 8px rgba(242, 195, 0, 0.2); }
        .player-card.bankrupt { opacity: 0.5; filter: grayscale(1); }
        .player-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .player-name { font-weight: 700; font-size: 1rem; }
        .player-money { color: #28a745; font-weight: 700; }
        .player-assets { font-size: 0.8rem; color: #666; margin-top: 5px; display: flex; gap: 5px; flex-wrap: wrap; }
        .asset-badge { background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; }

        .action-bar {
            background: #fff;
            padding: 15px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            justify-content: center;
            min-height: 70px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.1s, opacity 0.2s;
            font-size: 0.9rem;
        }
        .btn:active { transform: scale(0.95); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-accent { background: var(--accent); color: #222; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-outline { background: transparent; border: 1px solid #ccc; color: #555; }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .modal-overlay.show { opacity: 1; pointer-events: auto; }
        .modal {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            text-align: center;
        }

        .game-log {
            position: absolute;
            bottom: 80px;
            left: 20px;
            width: 300px;
            max-height: 150px;
            overflow-y: auto;
            background: rgba(255,255,255,0.9);
            padding: 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            pointer-events: none;
        }
        .log-entry { margin-bottom: 4px; border-bottom: 1px solid #eee; padding-bottom: 2px; }

        @media (max-width: 768px) {
            .game-area { flex-direction: column; }
            .sidebar { width: 100%; height: 180px; border-left: none; border-top: 1px solid #ddd; order: 2; }
            .board-wrapper { order: 1; padding: 10px; }
            .board { width: 98vw; height: 98vw; }
        }
    </style>
</head>
<body>

<div id="app">
    <header>
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-weight:bold; color:var(--accent);">MONOPOLY</span>
            <span style="opacity:0.7;">|</span>
            <span id="room-code">{{ $room->code }}</span>
        </div>
        <div>
            <button onclick="window.location.href='{{ route('menu') }}'" class="btn btn-outline" style="padding:5px 10px; font-size:0.8rem; color:#fff; border-color:rgba(255,255,255,0.3);">Keluar</button>
        </div>
    </header>

    <div class="game-area">
        <div class="board-wrapper">
            <div class="board" id="board">
                <div class="board-center">
                    <div class="logo-text">MONOPOLY</div>
                    <div id="center-message" style="z-index:10; font-size:1.2rem; font-weight:bold; color:var(--primary);">Menunggu...</div>
                    <div id="dice-display" style="margin-top:20px; font-size:2rem; display:none;">🎲 🎲</div>
                </div>
            </div>
            <div class="game-log" id="game-log"></div>
        </div>

        <aside class="sidebar">
            <div class="player-list" id="player-list"></div>
        </aside>
    </div>

    <div class="action-bar" id="action-bar">
        <button id="btn-roll" class="btn btn-primary" disabled>Melempar Dadu</button>
        <button id="btn-buy" class="btn btn-accent" style="display:none;">Beli ($<span id="buy-price">0</span>)</button>
        <button id="btn-decline" class="btn btn-outline" style="display:none;">Tolak</button>
        <button id="btn-end" class="btn btn-primary" style="display:none;">Akhiri Giliran</button>
        <button id="btn-pay-debt" class="btn btn-accent" style="display:none;">Bayar Utang</button>
        <button id="btn-bankrupt" class="btn btn-danger" style="display:none;">Bangkrut</button>
        <button id="btn-mortgage" class="btn btn-outline" style="display:none;">Gadai</button>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal">
        <h3 id="modal-title">Judul</h3>
        <p id="modal-body">Isi pesan...</p>
        <div style="margin-top:20px; display:flex; gap:10px; justify-content:center;">
            <button id="modal-confirm" class="btn btn-primary">OK</button>
            <button id="modal-cancel" class="btn btn-outline" style="display:none;">Batal</button>
        </div>
    </div>
</div>

<!-- GM Panel Trigger -->
@if($canGm ?? false)
    <button id="gm-trigger" onclick="document.getElementById('gmPanel').style.display='block'" 
            style="position:fixed; bottom:20px; right:20px; background:#dc3545; color:#fff; padding:12px 24px; border-radius:50px; border:none; cursor:pointer; box-shadow:0 4px 10px rgba(0,0,0,0.3); z-index:9999; font-weight:bold;">
        🛠️ GM Panel
    </button>
    @include('partials.gm-panel')
@endif

<script>
    const config = @json($clientData);
    const tiles = config.tiles;
    let state = config.snapshot;
    let expectedVersion = state.version;
    let myUserId = {{ auth()->id() }};
    let isLocalMode = state.room.mode === 'local';
    const roomCode = state.room.code;

    // DOM Elements
    const boardEl = document.getElementById('board');
    const playerListEl = document.getElementById('player-list');
    const logEl = document.getElementById('game-log');
    const centerMsg = document.getElementById('center-message');
    const diceDisplay = document.getElementById('dice-display');
    
    const btnRoll = document.getElementById('btn-roll');
    const btnBuy = document.getElementById('btn-buy');
    const btnDecline = document.getElementById('btn-decline');
    const btnEnd = document.getElementById('btn-end');
    const btnPayDebt = document.getElementById('btn-pay-debt');
    const btnBankrupt = document.getElementById('btn-bankrupt');
    const btnMortgage = document.getElementById('btn-mortgage');

    function init() {
        renderBoard();
        updateUI();
        connectWebSocket();
        
        btnRoll.addEventListener('click', () => sendAction('roll'));
        btnBuy.addEventListener('click', () => sendAction('buy'));
        btnDecline.addEventListener('click', () => sendAction('decline'));
        btnEnd.addEventListener('click', () => sendAction('end_turn'));
        btnPayDebt.addEventListener('click', () => sendAction('pay_debt'));
        btnBankrupt.addEventListener('click', () => sendAction('declare_bankruptcy'));
        btnMortgage.addEventListener('click', () => showModal('Gadai', 'Fitur gadai selektif akan segera hadir.'));
    }

    function connectWebSocket() {
        if (typeof window.Echo === 'undefined') {
            console.warn('Echo not loaded. Using polling.');
            startPolling();
            return;
        }

        window.Echo.private(`game.${roomCode}`)
            .listen('GameStateChanged', (e) => {
                if (e.version > expectedVersion) {
                    state = e.snapshot;
                    expectedVersion = e.version;
                    updateUI();
                    
                    // Auto-step AI jika giliran komputer
                    if (state.ai_turn && !isLocalMode) {
                        triggerAiStep();
                    }
                }
            })
            .error((error) => {
                console.error('WS Error:', error);
                startPolling();
            });
    }

    let pollingInterval = null;
    function startPolling() {
        if (pollingInterval) return;
        pollingInterval = setInterval(async () => {
            try {
                const res = await fetch(config.urls.state);
                const data = await res.json();
                if (data.version > expectedVersion) {
                    state = data;
                    expectedVersion = data.version;
                    updateUI();
                    if (state.ai_turn && !isLocalMode) triggerAiStep();
                }
            } catch (e) {}
        }, 2000);
    }

    async function triggerAiStep() {
        try {
            await fetch(config.urls.aiStep, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ expected_version: expectedVersion })
            });
        } catch (e) {}
    }

    function renderBoard() {
        const center = boardEl.querySelector('.board-center');
        boardEl.innerHTML = '';
        boardEl.appendChild(center);

        tiles.forEach(tile => {
            const el = document.createElement('div');
            el.className = `tile ${tile.type === 'corner' ? 'corner' : ''}`;
            el.id = `tile-${tile.id}`;
            
            let row, col;
            // Perbaikan Grid Positioning
            if (tile.id >= 0 && tile.id <= 10) { // Bottom (Right to Left)
                row = 11; col = 11 - tile.id;
            } else if (tile.id >= 11 && tile.id <= 20) { // Left (Bottom to Top)
                row = 11 - (tile.id - 10); col = 1;
            } else if (tile.id >= 21 && tile.id <= 30) { // Top (Left to Right)
                row = 1; col = tile.id - 20;
            } else { // Right (Top to Bottom)
                row = tile.id - 30; col = 11;
            }
            
            el.style.gridRow = row;
            el.style.gridColumn = col;

            if (tile.color) {
                el.innerHTML += `<div class="tile-header" style="background:${tile.color};"></div>`;
            }
            el.innerHTML += `<div class="tile-name">${tile.name}</div>`;
            if (tile.price) {
                el.innerHTML += `<div class="tile-price">$${tile.price}</div>`;
            }
            boardEl.appendChild(el);
        });
    }

    function updateUI() {
        playerListEl.innerHTML = '';
        let currentPlayerObj = null;

        state.players.forEach(p => {
            if (p.id === state.current_player_id) currentPlayerObj = p;
            
            const card = document.createElement('div');
            card.className = `player-card ${p.id === state.current_player_id ? 'active' : ''} ${p.is_bankrupt ? 'bankrupt' : ''}`;
            
            const props = state.properties.filter(prop => prop.owner_player_id === p.id);
            const houses = props.reduce((sum, prop) => sum + prop.houses, 0);
            const hotels = props.filter(prop => prop.has_hotel).length;

            // Gunakan textContent untuk keamanan XSS
            const nameSpan = document.createElement('span');
            nameSpan.className = 'player-name';
            nameSpan.style.color = p.token || '#333';
            nameSpan.textContent = p.name + (p.is_ai ? ' 🤖' : '');
            
            const moneySpan = document.createElement('span');
            moneySpan.className = 'player-money';
            moneySpan.textContent = `$${p.money}`;

            const header = document.createElement('div');
            header.className = 'player-header';
            header.appendChild(nameSpan);
            header.appendChild(moneySpan);
            card.appendChild(header);

            const assetsDiv = document.createElement('div');
            assetsDiv.className = 'player-assets';
            if (props.length > 0) assetsDiv.innerHTML += `<span class="asset-badge">🏠 ${props.length}</span>`;
            if (houses > 0) assetsDiv.innerHTML += `<span class="asset-badge">🏡 ${houses}</span>`;
            if (hotels > 0) assetsDiv.innerHTML += `<span class="asset-badge">🏨 ${hotels}</span>`;
            if (p.in_jail) assetsDiv.innerHTML += `<span class="asset-badge" style="background:#ffebee; color:#c62828;">⛓️ Penjara</span>`;
            card.appendChild(assetsDiv);

            playerListEl.appendChild(card);
        });

        // Update Tokens
        document.querySelectorAll('.token').forEach(t => t.remove());
        state.players.forEach(p => {
            if (p.is_bankrupt) return;
            const tileEl = document.getElementById(`tile-${p.position}`);
            if (tileEl) {
                const token = document.createElement('div');
                token.className = 'token';
                token.style.background = p.token || '#333';
                token.textContent = p.name.charAt(0);
                
                const countOnTile = state.players.filter(pl => pl.position === p.position && !pl.is_bankrupt).length;
                const index = state.players.findIndex(pl => pl.id === p.id);
                const offset = (index % countOnTile) * 5;
                
                token.style.transform = `translate(${offset}px, ${offset}px)`;
                tileEl.appendChild(token);
            }
        });

        // Update Properties Visuals
        state.properties.forEach(prop => {
            const tileEl = document.getElementById(`tile-${prop.tile_id}`);
            if (!tileEl) return;
            tileEl.querySelectorAll('.prop-marker').forEach(m => m.remove());

            if (prop.owner_player_id) {
                const owner = state.players.find(p => p.id === prop.owner_player_id);
                if (owner) {
                    tileEl.style.boxShadow = `inset 0 0 0 2px ${owner.token}`;
                    if (prop.has_hotel) {
                        const h = document.createElement('div');
                        h.className = 'prop-marker';
                        h.innerText = '🏨';
                        h.style.cssText = 'position:absolute;top:2px;right:2px;';
                        tileEl.appendChild(h);
                    } else if (prop.houses > 0) {
                        const h = document.createElement('div');
                        h.className = 'prop-marker';
                        h.innerText = '🏡'.repeat(prop.houses);
                        h.style.cssText = 'position:absolute;top:2px;right:2px;font-size:0.6rem;';
                        tileEl.appendChild(h);
                    }
                    if (prop.is_mortgaged) {
                        const m = document.createElement('div');
                        m.className = 'prop-marker';
                        m.innerText = 'M';
                        m.style.cssText = 'position:absolute;bottom:2px;left:2px;color:red;font-weight:bold;';
                        tileEl.appendChild(m);
                    }
                }
            } else {
                tileEl.style.boxShadow = 'none';
            }
        });

        // Update Controls based on state.actions
        const isMyTurn = state.can_act;
        
        btnRoll.style.display = 'none';
        btnBuy.style.display = 'none';
        btnDecline.style.display = 'none';
        btnEnd.style.display = 'none';
        btnPayDebt.style.display = 'none';
        btnBankrupt.style.display = 'none';

        if (state.actions.includes('roll')) {
            btnRoll.style.display = 'inline-block';
            btnRoll.disabled = false;
            if (currentPlayerObj && currentPlayerObj.in_jail) btnRoll.innerText = "Coba Keluar Penjara";
            else btnRoll.innerText = "Melempar Dadu";
        }
        if (state.actions.includes('buy')) {
            btnBuy.style.display = 'inline-block';
            btnDecline.style.display = 'inline-block';
            document.getElementById('buy-price').innerText = state.room.pending?.price || 0;
        }
        if (state.actions.includes('end_turn')) {
            btnEnd.style.display = 'inline-block';
        }
        if (state.actions.includes('pay_debt')) {
            btnPayDebt.style.display = 'inline-block';
            btnBankrupt.style.display = 'inline-block';
        }

        // Center Message
        if (currentPlayerObj) {
            centerMsg.textContent = `Giliran: ${currentPlayerObj.name}`;
            if (state.room.last_dice) {
                diceDisplay.style.display = 'block';
                diceDisplay.innerText = `🎲 ${state.room.last_dice.d1} + ${state.room.last_dice.d2} = ${state.room.last_dice.total}`;
            } else {
                diceDisplay.style.display = 'none';
            }
        }

        // Logs
        logEl.innerHTML = state.logs.slice().reverse().map(l => `<div class="log-entry">${l.message}</div>`).join('');
    }

    async function sendAction(action, params = {}) {
        const btn = event.target;
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Loading...';

        try {
            const url = config.urls.action.replace('__ACTION__', action);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Idempotency-Key': crypto.randomUUID()
                },
                body: JSON.stringify({ ...params, expected_version: expectedVersion })
            });
            
            const data = await response.json();
            if (response.ok) {
                if (data.version > expectedVersion) {
                    state = data;
                    expectedVersion = data.version;
                    updateUI();
                }
            } else {
                showModal('Error', data.message || 'Terjadi kesalahan.');
                btn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            showModal('Jaringan Error', 'Gagal terhubung ke server.');
        } finally {
            btn.disabled = false;
            btn.innerText = originalText;
        }
    }

    function showModal(title, body, onConfirm = null) {
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-body').textContent = body;
        document.getElementById('modal-overlay').classList.add('show');
        
        const confirmBtn = document.getElementById('modal-confirm');
        confirmBtn.onclick = () => {
            document.getElementById('modal-overlay').classList.remove('show');
            if (onConfirm) onConfirm();
        };
    }

    window.addEventListener('beforeunload', () => {
        if (typeof window.Echo !== 'undefined') {
            window.Echo.leaveChannel(`game.${roomCode}`);
        }
    });

    init();
</script>
</body>
</html>