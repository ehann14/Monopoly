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

        /* Layout Utama */
        #app {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        header {
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            backdrop-filter: blur(5px);
        }

        .game-area {
            flex: 1;
            display: flex;
            position: relative;
            overflow: hidden;
            background: #2c3e50;
        }

        /* Papan Monopoly */
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

        /* Tile Styles */
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

        .tile-header {
            height: 25%;
            width: 100%;
            border-bottom: 1px solid #000;
        }

        .tile-name {
            padding: 2px;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            word-break: break-word;
            font-weight: 600;
        }

        .tile-price {
            padding-bottom: 2px;
            font-size: 0.55rem;
        }

        /* Corner Tiles */
        .tile.corner {
            justify-content: center;
            align-items: center;
            font-weight: bold;
            font-size: 0.8rem;
            background: #e9ecef;
        }

        /* Center Area (Logo & Info) */
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

        /* Player Tokens */
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

        /* Sidebar Pemain */
        .sidebar {
            width: 320px;
            background: #fff;
            border-left: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            z-index: 50;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        }

        .player-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .player-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid transparent;
            transition: all 0.2s;
        }

        .player-card.active {
            background: #fffbe6;
            border-left-color: var(--accent);
            box-shadow: 0 2px 8px rgba(242, 195, 0, 0.2);
        }

        .player-card.bankrupt {
            opacity: 0.5;
            filter: grayscale(1);
        }

        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .player-name { font-weight: 700; font-size: 1rem; }
        .player-money { color: #28a745; font-weight: 700; }
        
        .player-assets {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .asset-badge {
            background: #eee;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        /* Action Bar */
        .action-bar {
            background: #fff;
            padding: 15px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            justify-content: center;
            min-height: 70px;
            align-items: center;
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

        /* Modal & Log */
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
            scrollbar-width: thin;
        }

        .log-entry { margin-bottom: 4px; border-bottom: 1px solid #eee; padding-bottom: 2px; }

        /* Responsive Mobile */
        @media (max-width: 768px) {
            .game-area { flex-direction: column; }
            .sidebar { width: 100%; height: 180px; border-left: none; border-top: 1px solid #ddd; order: 2; }
            .board-wrapper { order: 1; padding: 10px; }
            .board { width: 98vw; height: 98vw; }
            .tile-name { font-size: 0.4rem; }
            .logo-text { font-size: 1.5rem; }
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
                <!-- Tiles will be rendered here by JS -->
                <div class="board-center">
                    <div class="logo-text">MONOPOLY</div>
                    <div id="center-message" style="z-index:10; font-size:1.2rem; font-weight:bold; color:var(--primary);">
                        Menunggu permainan...
                    </div>
                    <div id="dice-display" style="margin-top:20px; font-size:2rem; display:none;">🎲 🎲</div>
                </div>
            </div>
            <div class="game-log" id="game-log"></div>
        </div>

        <aside class="sidebar">
            <div class="player-list" id="player-list">
                <!-- Player cards rendered by JS -->
            </div>
        </aside>
    </div>

    <div class="action-bar" id="action-bar">
        <button id="btn-roll" class="btn btn-primary" disabled>Melempar Dadu</button>
        <button id="btn-buy" class="btn btn-accent" style="display:none;">Beli ($<span id="buy-price">0</span>)</button>
        <button id="btn-decline" class="btn btn-outline" style="display:none;">Tolak</button>
        <button id="btn-end" class="btn btn-primary" style="display:none;">Akhiri Giliran</button>
        <button id="btn-build" class="btn btn-outline" style="display:none;">Bangun Rumah</button>
        <button id="btn-mortgage" class="btn btn-outline" style="display:none;">Gadai Properti</button>
    </div>
</div>

<!-- Modal Konfirmasi -->
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

<!-- GM Panel Trigger (Only for Owner) -->
@if(auth()->user()->is_owner ?? false)
    <button id="gm-trigger" onclick="document.getElementById('gmPanel').style.display='block'" 
            style="position:fixed; bottom:20px; right:20px; background:#dc3545; color:#fff; padding:12px 24px; border-radius:50px; border:none; cursor:pointer; box-shadow:0 4px 10px rgba(0,0,0,0.3); z-index:9999; font-weight:bold;">
        🛠️ GM Panel
    </button>
    @include('partials.gm-panel')
@endif

<script>
    // --- Configuration & State ---
    const config = @json($clientData);
    const tiles = config.tiles;
    const groups = config.groups;
    let state = config.snapshot;
    let expectedVersion = state.version;
    let myUserId = {{ auth()->id() }};
    let isLocalMode = state.room.mode === 'local';

    // --- DOM Elements ---
    const boardEl = document.getElementById('board');
    const playerListEl = document.getElementById('player-list');
    const logEl = document.getElementById('game-log');
    const centerMsg = document.getElementById('center-message');
    const diceDisplay = document.getElementById('dice-display');
    
    // Buttons
    const btnRoll = document.getElementById('btn-roll');
    const btnBuy = document.getElementById('btn-buy');
    const btnDecline = document.getElementById('btn-decline');
    const btnEnd = document.getElementById('btn-end');
    const btnBuild = document.getElementById('btn-build');

    // --- Initialization ---
    function init() {
        renderBoard();
        updateUI();
        startPolling();
        
        // Event Listeners
        btnRoll.addEventListener('click', () => sendAction('roll'));
        btnBuy.addEventListener('click', () => sendAction('buy'));
        btnDecline.addEventListener('click', () => sendAction('decline'));
        btnEnd.addEventListener('click', () => sendAction('end_turn'));
    }

    // --- Rendering Board ---
    function renderBoard() {
        // Clear existing tiles except center
        const center = boardEl.querySelector('.board-center');
        boardEl.innerHTML = '';
        boardEl.appendChild(center);

        tiles.forEach(tile => {
            const el = document.createElement('div');
            el.className = `tile ${tile.type === 'corner' ? 'corner' : ''}`;
            el.id = `tile-${tile.id}`;
            
            // Grid Positioning Logic for Monopoly Board (0-39)
            let row, col;
            if (tile.id >= 0 && tile.id <= 10) { // Bottom Row (Right to Left)
                row = 11; col = 11 - tile.id;
            } else if (tile.id >= 11 && tile.id <= 20) { // Left Column (Bottom to Top)
                row = 11 - (tile.id - 10); col = 1;
            } else if (tile.id >= 21 && tile.id <= 30) { // Top Row (Left to Right)
                row = 1; col = tile.id - 20;
            } else { // Right Column (Top to Bottom)
                row = tile.id - 30; col = 11;
            }
            
            el.style.gridRow = row;
            el.style.gridColumn = col;

            // Content
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

    // --- Updating UI ---
    function updateUI() {
        // 1. Update Players
        playerListEl.innerHTML = '';
        let currentPlayerObj = null;

        state.players.forEach(p => {
            if (p.id === state.current_player_id) currentPlayerObj = p;
            
            const card = document.createElement('div');
            card.className = `player-card ${p.id === state.current_player_id ? 'active' : ''} ${p.is_bankrupt ? 'bankrupt' : ''}`;
            
            // Count assets
            const props = state.properties.filter(prop => prop.owner_player_id === p.id);
            const houses = props.reduce((sum, prop) => sum + prop.houses, 0);
            const hotels = props.filter(prop => prop.has_hotel).length;

            card.innerHTML = `
                <div class="player-header">
                    <span class="player-name" style="color:${p.token || '#333'}">${p.name} ${p.is_ai ? '🤖' : ''}</span>
                    <span class="player-money">$${p.money}</span>
                </div>
                <div class="player-assets">
                    ${props.length > 0 ? `<span class="asset-badge">🏠 ${props.length}</span>` : ''}
                    ${houses > 0 ? `<span class="asset-badge">🏡 ${houses}</span>` : ''}
                    ${hotels > 0 ? `<span class="asset-badge">🏨 ${hotels}</span>` : ''}
                    ${p.in_jail ? '<span class="asset-badge" style="background:#ffebee; color:#c62828;">⛓️ Penjara</span>' : ''}
                </div>
            `;
            playerListEl.appendChild(card);
        });

        // 2. Update Tokens on Board
        document.querySelectorAll('.token').forEach(t => t.remove());
        state.players.forEach(p => {
            if (p.is_bankrupt) return;
            const tileEl = document.getElementById(`tile-${p.position}`);
            if (tileEl) {
                const token = document.createElement('div');
                token.className = 'token';
                token.style.background = p.token || '#333';
                token.innerText = p.name.charAt(0);
                
                // Offset multiple tokens on same tile
                const countOnTile = state.players.filter(pl => pl.position === p.position && !pl.is_bankrupt).length;
                const index = state.players.findIndex(pl => pl.id === p.id);
                const offset = (index % countOnTile) * 5;
                
                token.style.transform = `translate(${offset}px, ${offset}px)`;
                tileEl.appendChild(token);
            }
        });

        // 3. Update Properties (Colors/Houses)
        state.properties.forEach(prop => {
            const tileEl = document.getElementById(`tile-${prop.tile_id}`);
            if (!tileEl) return;
            
            // Remove old markers
            tileEl.querySelectorAll('.prop-marker').forEach(m => m.remove());

            if (prop.owner_player_id) {
                const owner = state.players.find(p => p.id === prop.owner_player_id);
                if (owner) {
                    tileEl.style.boxShadow = `inset 0 0 0 2px ${owner.token}`;
                    
                    // Houses/Hotels
                    if (prop.has_hotel) {
                        const hotel = document.createElement('div');
                        hotel.className = 'prop-marker';
                        hotel.innerText = '🏨';
                        hotel.style.position = 'absolute';
                        hotel.style.top = '2px';
                        hotel.style.right = '2px';
                        tileEl.appendChild(hotel);
                    } else if (prop.houses > 0) {
                        const houses = document.createElement('div');
                        houses.className = 'prop-marker';
                        houses.innerText = '🏡'.repeat(prop.houses);
                        houses.style.position = 'absolute';
                        houses.style.top = '2px';
                        houses.style.right = '2px';
                        houses.style.fontSize = '0.6rem';
                        tileEl.appendChild(houses);
                    }
                    
                    if (prop.is_mortgaged) {
                        const mark = document.createElement('div');
                        mark.className = 'prop-marker';
                        mark.innerText = 'M';
                        mark.style.position = 'absolute';
                        mark.style.bottom = '2px';
                        mark.style.left = '2px';
                        mark.style.color = 'red';
                        mark.style.fontWeight = 'bold';
                        tileEl.appendChild(mark);
                    }
                }
            } else {
                tileEl.style.boxShadow = 'none';
            }
        });

        // 4. Update Controls
        const isMyTurn = currentPlayerObj && (currentPlayerObj.user_id === myUserId || isLocalMode) && !currentPlayerObj.is_ai;
        
        btnRoll.style.display = 'none';
        btnBuy.style.display = 'none';
        btnDecline.style.display = 'none';
        btnEnd.style.display = 'none';
        btnBuild.style.display = 'none';

        if (state.turn_phase === 'await_roll' && isMyTurn && !currentPlayerObj.in_jail) {
            btnRoll.style.display = 'inline-block';
            btnRoll.disabled = false;
        } else if (state.turn_phase === 'await_buy' && isMyTurn) {
            btnBuy.style.display = 'inline-block';
            btnDecline.style.display = 'inline-block';
            document.getElementById('buy-price').innerText = state.pending.price;
        } else if (state.turn_phase === 'await_end' && isMyTurn) {
            btnEnd.style.display = 'inline-block';
            // Show build button if owns complete set (simplified check)
            btnBuild.style.display = 'inline-block'; 
        } else if (currentPlayerObj.in_jail && isMyTurn) {
             btnRoll.style.display = 'inline-block';
             btnRoll.innerText = "Coba Keluar Penjara";
        }

        // 5. Center Message
        if (currentPlayerObj) {
            centerMsg.innerText = `Giliran: ${currentPlayerObj.name}`;
            if (state.last_dice) {
                diceDisplay.style.display = 'block';
                diceDisplay.innerText = `🎲 ${state.last_dice.d1} + ${state.last_dice.d2} = ${state.last_dice.total}`;
            } else {
                diceDisplay.style.display = 'none';
            }
        }

        // 6. Logs
        logEl.innerHTML = state.logs.slice().reverse().map(l => `<div class="log-entry">${l.message}</div>`).join('');
    }

    // --- Actions ---
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
                state = data;
                expectedVersion = data.version;
                updateUI();
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

    // --- Polling ---
    function startPolling() {
        setInterval(async () => {
            try {
                const res = await fetch(config.urls.state);
                const data = await res.json();
                if (data.version > expectedVersion) {
                    state = data;
                    expectedVersion = data.version;
                    updateUI();
                }
            } catch (e) {}
        }, 2000);
    }

    // --- Modal Helper ---
    function showModal(title, body, onConfirm = null) {
        document.getElementById('modal-title').innerText = title;
        document.getElementById('modal-body').innerText = body;
        document.getElementById('modal-overlay').classList.add('show');
        
        const confirmBtn = document.getElementById('modal-confirm');
        confirmBtn.onclick = () => {
            document.getElementById('modal-overlay').classList.remove('show');
            if (onConfirm) onConfirm();
        };
    }

    // Start
    init();
</script>
</body>
</html>