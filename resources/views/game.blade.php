<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $room->code }} — Monopoly Web Game</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --primary: #0f3d2e;
            --primary-2: #165a44;
            --accent: #f2c300;
            --danger: #dc3545;
            --ok: #28a745;
            --board-bg: #cde6d0;
            --ink: #1f2933;
            --muted: #6b7785;
            --line: #e4e8ec;
        }
        * { box-sizing: border-box; }

        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #12352a;
            color: var(--ink);
            overflow: hidden;
        }
        button { font-family: inherit; }

        #app { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }

        /* ---------- Header ---------- */
        header {
            background: rgba(0,0,0,.82);
            color: #fff;
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            z-index: 100;
            flex: none;
        }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .brand b { color: var(--accent); letter-spacing: .08em; }
        .room-chip {
            background: rgba(255,255,255,.12); border-radius: 6px; padding: 2px 8px;
            font-family: ui-monospace, Consolas, monospace; letter-spacing: .1em;
        }
        .head-right { display: flex; align-items: center; gap: 12px; }
        .turn-pill {
            font-size: .8rem; padding: 3px 10px; border-radius: 999px;
            background: rgba(255,255,255,.12); white-space: nowrap;
        }
        .turn-pill.mine { background: var(--accent); color: #222; font-weight: 700; }
        .conn { font-size: .75rem; opacity: .8; white-space: nowrap; }
        .conn i { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #9aa5b1; margin-right: 4px; }
        .conn.live i { background: #4cd964; }
        .conn.poll i { background: #ffb300; }

        /* ---------- Layout ---------- */
        .game-area { flex: 1; display: flex; min-height: 0; background: #1d4d3c; }

        .board-wrapper {
            flex: 1; min-width: 0; min-height: 0;
            display: flex; justify-content: center; align-items: center;
            padding: 14px;
            container-type: size;
        }

        /* ---------- Board ---------- */
        .board {
            width: min(100cqw, 100cqh);
            height: min(100cqw, 100cqh);
            background: var(--board-bg);
            border: 3px solid #0b2a20;
            border-radius: 6px;
            display: grid;
            grid-template-columns: 1.5fr repeat(9, 1fr) 1.5fr;
            grid-template-rows: 1.5fr repeat(9, 1fr) 1.5fr;
            gap: 1px;
            background-color: #0b2a20;
            box-shadow: 0 10px 30px rgba(0,0,0,.45);
        }

        .tile {
            position: relative;
            background: #fbfdfb;
            display: flex; flex-direction: column;
            font-size: clamp(.42rem, 1.15vmin, .72rem);
            line-height: 1.15;
            text-align: center;
            overflow: hidden;
            cursor: pointer;
            min-width: 0; min-height: 0;
            transition: background .15s;
        }
        .tile:hover { background: #fffbe6; }
        .tile:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
        .tile-header { height: 22%; width: 100%; flex: none; border-bottom: 1px solid rgba(0,0,0,.6); }
        .tile-body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1px 2px; min-height: 0; }
        .tile-icon { font-size: 1.5em; line-height: 1; }
        .tile-name { font-weight: 700; word-break: break-word; hyphens: auto; }
        .tile-price { color: #44505c; font-size: .9em; padding-bottom: 1px; }
        .tile.corner { background: #e6efe7; }
        .tile.corner .tile-icon { font-size: 2.1em; }
        .tile.corner .tile-name { font-size: 1.05em; }

        .tile .owner-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 5px; display: none; }
        .tile.owned .owner-bar { display: block; }
        .tile.mortgaged { background: repeating-linear-gradient(45deg, #f3f3f3, #f3f3f3 6px, #e3e3e3 6px, #e3e3e3 12px); }
        .tile.mortgaged .tile-name::after { content: ' (gadai)'; color: var(--danger); font-weight: 600; }
        .tile.current { box-shadow: inset 0 0 0 3px var(--accent); animation: pulse 1.6s infinite; }
        @keyframes pulse { 50% { box-shadow: inset 0 0 0 3px rgba(242,195,0,.35); } }

        .markers { position: absolute; top: 1px; right: 2px; font-size: .95em; line-height: 1; text-shadow: 0 1px 0 #fff; }

        .tokens {
            position: absolute; left: 0; right: 0; bottom: 6px;
            display: flex; flex-wrap: wrap; justify-content: center; gap: 2px;
            pointer-events: none;
        }
        .token {
            width: clamp(11px, 2.3vmin, 20px); height: clamp(11px, 2.3vmin, 20px);
            border-radius: 50%; border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,.5);
            display: flex; align-items: center; justify-content: center;
            font-size: clamp(7px, 1.2vmin, 11px); font-weight: 800;
            animation: drop .35s ease-out;
        }
        @keyframes drop { from { transform: translateY(-14px) scale(1.3); opacity: 0; } }

        /* ---------- Board center ---------- */
        .board-center {
            grid-column: 2 / 11; grid-row: 2 / 11;
            background: radial-gradient(circle at 50% 40%, #ffffff, #eef5ef);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 10px; padding: 12px; text-align: center; position: relative; overflow: hidden;
        }
        .logo-text {
            position: absolute; font-size: clamp(2rem, 9vmin, 5rem); font-weight: 900; color: var(--primary);
            transform: rotate(-35deg); opacity: .07; pointer-events: none; letter-spacing: .1em;
        }
        #center-title { z-index: 1; font-size: clamp(.9rem, 2.6vmin, 1.5rem); font-weight: 800; color: var(--primary); }
        #center-sub { z-index: 1; font-size: clamp(.7rem, 1.8vmin, 1rem); color: var(--muted); max-width: 90%; }
        .dice { z-index: 1; display: flex; gap: 10px; align-items: center; min-height: clamp(36px, 8vmin, 64px); }
        .die {
            width: clamp(34px, 8vmin, 62px); height: clamp(34px, 8vmin, 62px);
            background: #fff; border: 2px solid #333; border-radius: 12%;
            display: grid; place-items: center; font-size: clamp(1.5rem, 5.8vmin, 3.2rem); line-height: 1;
            box-shadow: 0 3px 0 #333, 0 6px 10px rgba(0,0,0,.2);
        }
        .dice.rolling .die { animation: tumble .55s ease-out; }
        @keyframes tumble { 0% { transform: rotate(-200deg) scale(.6); opacity: .2; } 60% { transform: rotate(20deg) scale(1.15); } 100% { transform: none; } }
        .dice-total { font-weight: 800; font-size: clamp(.9rem, 2.6vmin, 1.4rem); }
        .double-tag { background: var(--accent); color: #222; font-size: .7rem; font-weight: 800; padding: 1px 8px; border-radius: 999px; }

        /* ---------- Sidebar ---------- */
        .sidebar {
            width: 340px; flex: none; background: #fff;
            display: flex; flex-direction: column; min-height: 0;
            box-shadow: -2px 0 12px rgba(0,0,0,.2);
        }
        .side-tabs { display: none; }
        .panel { display: flex; flex-direction: column; min-height: 0; }
        .panel-title { font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); padding: 10px 14px 4px; }
        #player-panel { flex: 1 1 55%; }
        #log-panel { flex: 1 1 45%; border-top: 1px solid var(--line); }
        .scroll { overflow-y: auto; padding: 6px 12px 12px; flex: 1; min-height: 0; }

        .player-card {
            background: #f6f8f7; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px;
            border-left: 5px solid #ccc; transition: background .2s, box-shadow .2s;
        }
        .player-card.active { background: #fffbe6; box-shadow: 0 2px 10px rgba(242,195,0,.35); }
        .player-card.bankrupt { opacity: .45; filter: grayscale(1); }
        .player-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .player-name { font-weight: 700; display: flex; align-items: center; gap: 6px; min-width: 0; }
        .player-name span.n { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dot { width: 12px; height: 12px; border-radius: 50%; flex: none; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.25); }
        .player-money { color: #1b7f37; font-weight: 800; font-variant-numeric: tabular-nums; }
        .player-assets { margin-top: 6px; display: flex; gap: 5px; flex-wrap: wrap; }
        .badge { background: #e8ecef; padding: 1px 7px; border-radius: 999px; font-size: .72rem; }
        .badge.warn { background: #ffebee; color: #c62828; }
        .badge.you { background: var(--primary); color: #fff; }
        .badge.turn { background: var(--accent); color: #222; font-weight: 700; }

        .log-entry { font-size: .8rem; padding: 4px 0; border-bottom: 1px dashed var(--line); display: flex; gap: 6px; }
        .log-entry .ic { flex: none; width: 1.3em; text-align: center; }
        .log-entry.dice, .log-entry.move { color: #1f4fbf; }
        .log-entry.pay, .log-entry.bankrupt, .log-entry.debt, .log-entry.jail { color: #b3261e; }
        .log-entry.salary, .log-entry.buy, .log-entry.build { color: #1b7f37; }

        /* ---------- Action bar ---------- */
        .action-bar {
            flex: none; background: #fff; border-top: 1px solid var(--line);
            padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
            display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap; min-height: 64px;
        }
        .wait-msg { color: var(--muted); font-size: .95rem; display: flex; align-items: center; gap: 8px; }
        .spinner { width: 14px; height: 14px; border: 2px solid #cfd6dc; border-top-color: var(--primary); border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn {
            padding: 10px 18px; border: 1px solid transparent; border-radius: 8px;
            font-weight: 700; cursor: pointer; font-size: .92rem;
            transition: transform .1s, opacity .2s, filter .2s;
        }
        .btn:hover:not(:disabled) { filter: brightness(1.08); }
        .btn:active:not(:disabled) { transform: scale(.96); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-accent { background: var(--accent); color: #222; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-outline { background: #fff; border-color: #c9d1d8; color: #44505c; }
        .btn-sm { padding: 5px 10px; font-size: .8rem; border-radius: 6px; }
        .btn-ghost { background: transparent; color: #fff; border-color: rgba(255,255,255,.35); padding: 4px 10px; font-size: .8rem; }

        /* ---------- Modal ---------- */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.55);
            display: flex; justify-content: center; align-items: center;
            z-index: 1000; opacity: 0; pointer-events: none; transition: opacity .18s; padding: 16px;
        }
        .modal-overlay.show { opacity: 1; pointer-events: auto; }
        .modal {
            background: #fff; border-radius: 14px; width: 100%; max-width: 420px; max-height: 88vh;
            display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 16px 40px rgba(0,0,0,.35); transform: translateY(8px); transition: transform .18s;
        }
        .modal-overlay.show .modal { transform: none; }
        .modal-head { padding: 14px 18px; font-weight: 800; font-size: 1.05rem; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 10px; }
        .modal-head .swatch { width: 14px; height: 28px; border-radius: 4px; flex: none; }
        .modal-body { padding: 14px 18px; overflow-y: auto; font-size: .92rem; }
        .modal-foot { padding: 12px 18px; border-top: 1px solid var(--line); display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
        .kv { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dashed var(--line); }
        .kv span:first-child { color: var(--muted); }
        .kv.hl { background: #fffbe6; font-weight: 700; }
        .manage-row { display: flex; align-items: center; gap: 8px; padding: 7px 0; border-bottom: 1px solid var(--line); }
        .manage-row .nm { flex: 1; font-weight: 600; min-width: 0; }
        .winner { text-align: center; font-size: 3rem; }

        /* ---------- Toast ---------- */
        #toasts { position: fixed; top: 58px; left: 50%; transform: translateX(-50%); z-index: 3000; display: flex; flex-direction: column; gap: 8px; align-items: center; pointer-events: none; }
        .toast {
            background: #263238; color: #fff; padding: 9px 16px; border-radius: 8px; font-size: .88rem;
            box-shadow: 0 6px 16px rgba(0,0,0,.3); animation: tin .2s ease-out; max-width: 92vw;
        }
        .toast.error { background: #b3261e; }
        .toast.ok { background: #1b7f37; }
        @keyframes tin { from { transform: translateY(-10px); opacity: 0; } }

        /* ---------- Responsive ---------- */
        @media (max-width: 900px) {
            .game-area { flex-direction: column; }
            .board-wrapper { flex: none; height: min(100vw, 50vh); height: min(100vw, 50dvh); padding: 6px; }
            .sidebar { width: 100%; flex: 1; box-shadow: none; border-top: 1px solid #0b2a20; }
            .side-tabs { display: flex; border-bottom: 1px solid var(--line); flex: none; }
            .side-tabs button { flex: 1; padding: 8px; border: 0; background: #f6f8f7; font-weight: 700; color: var(--muted); cursor: pointer; }
            .side-tabs button.on { background: #fff; color: var(--primary); box-shadow: inset 0 -3px 0 var(--primary); }
            .sidebar[data-tab="players"] #log-panel { display: none; }
            .sidebar[data-tab="log"] #player-panel { display: none; }
            #player-panel, #log-panel { flex: 1; border: 0; }
            .panel-title { display: none; }
            .conn { display: none; }
            .action-bar { padding-top: 8px; min-height: 56px; }
            .btn { padding: 9px 14px; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>

<div id="app">
    <header>
        <div class="brand">
            <b>MONOPOLY</b>
            <span class="room-chip" title="Kode room">{{ $room->code }}</span>
        </div>
        <div class="head-right">
            <span class="turn-pill" id="turn-pill">…</span>
            <span class="conn" id="conn"><i></i><span>Menghubungkan</span></span>
            <button class="btn btn-ghost" id="btn-exit">Keluar</button>
        </div>
    </header>

    <div class="game-area">
        <div class="board-wrapper">
            <div class="board" id="board">
                <div class="board-center">
                    <div class="logo-text">MONOPOLY</div>
                    <div id="center-title">Memuat…</div>
                    <div class="dice" id="dice"></div>
                    <div id="center-sub"></div>
                </div>
            </div>
        </div>

        <aside class="sidebar" id="sidebar" data-tab="players">
            <div class="side-tabs">
                <button class="on" data-tab="players">👥 Pemain</button>
                <button data-tab="log">📜 Riwayat</button>
            </div>
            <section class="panel" id="player-panel">
                <div class="panel-title">Pemain</div>
                <div class="scroll" id="player-list"></div>
            </section>
            <section class="panel" id="log-panel">
                <div class="panel-title">Riwayat</div>
                <div class="scroll" id="game-log"></div>
            </section>
        </aside>
    </div>

    <div class="action-bar" id="action-bar"></div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal-overlay" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-head"><span class="swatch" id="modal-swatch" style="display:none"></span><span id="modal-title"></span></div>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-foot" id="modal-foot"></div>
    </div>
</div>
<div id="toasts" aria-live="polite"></div>

<!-- GM Panel -->
@if($canGm ?? false)
    <button id="gm-trigger" onclick="document.getElementById('gmPanel').style.display='block'"
            style="position:fixed; bottom:84px; right:16px; background:#dc3545; color:#fff; padding:10px 18px; border-radius:999px; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,.35); z-index:900; font-weight:700;">
        🛠️ GM
    </button>
    @include('partials.gm-panel')
@endif

<script>
(function () {
    'use strict';

    // ====================================================== data & util
    const config = @json($clientData);
    const TILES = config.tiles;
    const GROUPS = config.groups || {};
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const MY_USER_ID = {{ (int) auth()->id() }};
    const ROOM_CODE = config.snapshot.room.code;

    let state = config.snapshot;
    let version = state.version;
    let busy = false;
    let aiBusy = false;
    let lastDiceKey = null;
    let winnerShown = false;

    const TOKEN_COLORS = {
        merah:  { bg: '#e53935', fg: '#fff' },
        biru:   { bg: '#1e88e5', fg: '#fff' },
        hijau:  { bg: '#43a047', fg: '#fff' },
        kuning: { bg: '#fdd835', fg: '#222' },
    };
    const tokenOf = p => TOKEN_COLORS[p.token] || { bg: '#607d8b', fg: '#fff' };
    const money = n => '$' + Number(n || 0).toLocaleString('id-ID');
    const DIE = ['', '⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];

    const CORNERS = ['start', 'jail', 'free_parking', 'go_to_jail'];
    const TYPE_ICON = {
        start: '▶️', jail: '⛓️', free_parking: '🅿️', go_to_jail: '👮',
        chance: '❓', chest: '📦', tax: '💸', station: '🚉', utility: '💡',
    };
    const PHASE_LABEL = {
        await_roll: 'Lempar dadu',
        await_buy: 'Beli properti?',
        await_end: 'Akhiri giliran',
        await_debt: 'Lunasi utang',
    };
    const LOG_ICON = {
        dice: '🎲', move: '👣', buy: '🏷️', pay: '💸', salary: '💰', build: '🏠', sell: '🔨',
        mortgage: '📉', unmortgage: '📈', jail: '⛓️', card: '🃏', card_effect: '🃏', offer: '📢',
        decline: '🚫', end: '⏭️', turn: '🔁', bankrupt: '💀', debt: '⚠️', rule: '📜', info: 'ℹ️', system: '⚙️',
    };

    const $ = id => document.getElementById(id);
    const el = (tag, cls, text) => {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    };
    const uuid = () => (window.crypto && crypto.randomUUID)
        ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 3 | 8)).toString(16);
        });

    const currentPlayer = () => state.players.find(p => p.id === state.room.current_player_id) || null;
    const tileById = id => TILES.find(t => t.id === id);
    const propOf = id => state.properties.find(p => p.tile_id === id);
    const playerById = id => state.players.find(p => p.id === id);

    // ====================================================== toast & modal
    function toast(msg, type) {
        const t = el('div', 'toast ' + (type || ''), msg);
        $('toasts').appendChild(t);
        setTimeout(() => t.remove(), type === 'error' ? 4500 : 2600);
    }
    window.toast = toast;

    let modalCleanup = null;
    function openModal({ title, body, buttons, color, onClose }) {
        $('modal-title').textContent = title;
        const sw = $('modal-swatch');
        sw.style.display = color ? 'block' : 'none';
        if (color) sw.style.background = color;

        const bodyEl = $('modal-body');
        bodyEl.innerHTML = '';
        if (typeof body === 'string') bodyEl.textContent = body;
        else if (body) bodyEl.appendChild(body);

        const foot = $('modal-foot');
        foot.innerHTML = '';
        (buttons && buttons.length ? buttons : [{ label: 'OK', cls: 'btn-primary' }]).forEach(b => {
            const btn = el('button', 'btn ' + (b.cls || 'btn-outline'), b.label);
            if (b.disabled) btn.disabled = true;
            if (b.title) btn.title = b.title;
            btn.addEventListener('click', async () => {
                if (b.onClick) {
                    btn.disabled = true;
                    try { await b.onClick(); } finally { btn.disabled = !!b.disabled; }
                }
                if (!b.keepOpen) closeModal();
            });
            foot.appendChild(btn);
        });
        modalCleanup = onClose || null;
        $('modal-overlay').classList.add('show');
    }
    function closeModal() {
        $('modal-overlay').classList.remove('show');
        if (modalCleanup) { const f = modalCleanup; modalCleanup = null; f(); }
    }
    const modalOpen = () => $('modal-overlay').classList.contains('show');

    // ====================================================== board
    function gridPos(id) {
        if (id <= 10) return { row: 11, col: 11 - id };            // bawah, kanan → kiri
        if (id <= 20) return { row: 11 - (id - 10), col: 1 };      // kiri, bawah → atas
        if (id <= 30) return { row: 1, col: id - 19 };             // atas, kiri → kanan
        return { row: id - 29, col: 11 };                          // kanan, atas → bawah
    }

    function renderBoard() {
        const board = $('board');
        TILES.forEach(t => {
            const pos = gridPos(t.id);
            const isCorner = CORNERS.includes(t.type);
            const tile = el('div', 'tile' + (isCorner ? ' corner' : ''));
            tile.id = 'tile-' + t.id;
            tile.style.gridRow = pos.row;
            tile.style.gridColumn = pos.col;
            tile.tabIndex = 0;
            tile.setAttribute('role', 'button');
            tile.setAttribute('aria-label', t.name);

            const group = t.group && GROUPS[t.group];
            if (group) {
                const h = el('div', 'tile-header');
                h.style.background = group.color;
                tile.appendChild(h);
            }
            const body = el('div', 'tile-body');
            if (TYPE_ICON[t.type]) body.appendChild(el('div', 'tile-icon', TYPE_ICON[t.type]));
            body.appendChild(el('div', 'tile-name', t.name));
            const price = t.price || t.amount;
            if (price) body.appendChild(el('div', 'tile-price', money(price)));
            tile.appendChild(body);

            tile.appendChild(el('div', 'owner-bar'));
            tile.appendChild(el('div', 'markers'));
            tile.appendChild(el('div', 'tokens'));

            tile.addEventListener('click', () => showTileModal(t.id));
            tile.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showTileModal(t.id); } });
            board.appendChild(tile);
        });
    }

    function updateBoard() {
        const cur = currentPlayer();
        TILES.forEach(t => {
            const tile = $('tile-' + t.id);
            if (!tile) return;
            tile.classList.remove('owned', 'mortgaged');
            tile.classList.toggle('current', !!cur && cur.position === t.id && !cur.is_bankrupt);
            tile.querySelector('.tokens').innerHTML = '';
            tile.querySelector('.markers').textContent = '';
            tile.querySelector('.owner-bar').style.background = '';
        });

        state.properties.forEach(prop => {
            const tile = $('tile-' + prop.tile_id);
            if (!tile) return;
            const owner = prop.owner_player_id ? playerById(prop.owner_player_id) : null;
            if (owner && !owner.is_bankrupt) {
                tile.classList.add('owned');
                tile.querySelector('.owner-bar').style.background = tokenOf(owner).bg;
            }
            if (prop.is_mortgaged) tile.classList.add('mortgaged');
            tile.querySelector('.markers').textContent = prop.has_hotel ? '🏨' : (prop.houses > 0 ? '🏠'.repeat(prop.houses) : '');
        });

        state.players.forEach(p => {
            if (p.is_bankrupt) return;
            const tile = $('tile-' + p.position);
            if (!tile) return;
            const c = tokenOf(p);
            const tk = el('div', 'token', (p.name || '?').charAt(0).toUpperCase());
            tk.style.background = c.bg;
            tk.style.color = c.fg;
            tk.title = p.name;
            tile.querySelector('.tokens').appendChild(tk);
        });
    }

    // ====================================================== center
    function updateCenter() {
        const cur = currentPlayer();
        const room = state.room;
        const title = $('center-title');
        const sub = $('center-sub');

        if (room.status === 'finished') {
            const w = playerById(room.winner_player_id);
            title.textContent = w ? `🏆 ${w.name} menang!` : 'Permainan selesai';
            sub.textContent = '';
        } else if (cur) {
            title.textContent = `Giliran: ${cur.name}`;
            let text = PHASE_LABEL[room.turn_phase] || '';
            if (room.turn_phase === 'await_buy' && room.pending && room.pending.tile_id != null) {
                const t = tileById(room.pending.tile_id);
                text = `Beli ${t ? t.name : 'properti'} seharga ${money(room.pending.price)}?`;
            }
            if (room.turn_phase === 'await_roll' && cur.in_jail) text = 'Di penjara — coba lempar kembar atau bayar denda';
            if (room.doubles_count > 0 && room.turn_phase === 'await_roll') text += ' (dadu kembar, lempar lagi!)';
            sub.textContent = text;
        }

        const dice = $('dice');
        const ld = room.last_dice;
        if (!ld) { dice.innerHTML = ''; lastDiceKey = null; return; }
        const key = [ld.d1, ld.d2, room.turn_number, room.doubles_count, version].join('-');
        const changed = lastDiceKey !== null && lastDiceKey !== key;
        lastDiceKey = key;
        dice.innerHTML = '';
        dice.appendChild(el('div', 'die', DIE[ld.d1] || ld.d1));
        dice.appendChild(el('div', 'die', DIE[ld.d2] || ld.d2));
        dice.appendChild(el('div', 'dice-total', '= ' + ld.total));
        if (ld.double) dice.appendChild(el('span', 'double-tag', 'KEMBAR'));
        if (changed) {
            dice.classList.remove('rolling');
            void dice.offsetWidth;
            dice.classList.add('rolling');
        }
    }

    // ====================================================== sidebar
    function updatePlayers() {
        const list = $('player-list');
        list.innerHTML = '';
        const localMode = state.room.mode === 'local';

        state.players.forEach(p => {
            const c = tokenOf(p);
            const active = p.id === state.room.current_player_id && state.room.status === 'playing';
            const card = el('div', 'player-card' + (active ? ' active' : '') + (p.is_bankrupt ? ' bankrupt' : ''));
            card.style.borderLeftColor = c.bg;

            const top = el('div', 'player-top');
            const name = el('div', 'player-name');
            const dot = el('span', 'dot');
            dot.style.background = c.bg;
            name.appendChild(dot);
            name.appendChild(el('span', 'n', p.name + (p.is_ai ? ' 🤖' : '')));
            top.appendChild(name);
            top.appendChild(el('span', 'player-money', money(p.money)));
            card.appendChild(top);

            const props = state.properties.filter(x => x.owner_player_id === p.id);
            const houses = props.reduce((s, x) => s + (x.houses || 0), 0);
            const hotels = props.filter(x => x.has_hotel).length;

            const assets = el('div', 'player-assets');
            if (active) assets.appendChild(el('span', 'badge turn', 'Giliran'));
            if (!localMode && p.user_id === MY_USER_ID) assets.appendChild(el('span', 'badge you', 'Anda'));
            if (props.length) assets.appendChild(el('span', 'badge', '🏘️ ' + props.length));
            if (houses) assets.appendChild(el('span', 'badge', '🏠 ' + houses));
            if (hotels) assets.appendChild(el('span', 'badge', '🏨 ' + hotels));
            if (p.jail_cards > 0) assets.appendChild(el('span', 'badge', '🎫 ' + p.jail_cards));
            if (p.in_jail) assets.appendChild(el('span', 'badge warn', '⛓️ Penjara'));
            if (p.is_bankrupt) assets.appendChild(el('span', 'badge warn', '💀 Bangkrut'));
            if (p.user_id !== null && !p.is_ai && !p.is_connected) assets.appendChild(el('span', 'badge', '📴 Offline'));
            card.appendChild(assets);

            list.appendChild(card);
        });
    }

    function updateLog() {
        const box = $('game-log');
        box.innerHTML = '';
        state.logs.slice().reverse().forEach(l => {
            const row = el('div', 'log-entry ' + (l.type || ''));
            row.appendChild(el('span', 'ic', LOG_ICON[l.type] || '•'));
            const msg = el('span');
            msg.innerHTML = l.message; // sudah di-escape oleh server
            row.appendChild(msg);
            box.appendChild(row);
        });
    }

    function updateHeader() {
        const pill = $('turn-pill');
        const cur = currentPlayer();
        pill.classList.toggle('mine', !!state.can_act);
        if (state.room.status === 'finished') pill.textContent = 'Selesai';
        else if (state.can_act) pill.textContent = 'Giliran Anda';
        else pill.textContent = cur ? `Giliran ${cur.name}` : '…';
    }

    // ====================================================== actions bar
    function actionButton(label, cls, onClick, opts = {}) {
        const b = el('button', 'btn ' + cls, label);
        if (opts.disabled) b.disabled = true;
        if (opts.title) b.title = opts.title;
        b.addEventListener('click', onClick);
        $('action-bar').appendChild(b);
        return b;
    }

    function updateActions() {
        const bar = $('action-bar');
        bar.innerHTML = '';
        const room = state.room;

        if (room.status === 'finished') {
            actionButton('Kembali ke Menu', 'btn-primary', () => { location.href = config.urls.menu; });
            return;
        }

        const cur = currentPlayer();
        if (!state.can_act) {
            const w = el('div', 'wait-msg');
            w.appendChild(el('div', 'spinner'));
            w.appendChild(el('span', '', state.ai_turn
                ? `${cur ? cur.name : 'Komputer'} sedang berpikir…`
                : `Menunggu ${cur ? cur.name : 'pemain lain'}…`));
            bar.appendChild(w);
            return;
        }

        const a = state.actions || [];
        const has = x => a.includes(x);

        if (has('roll')) actionButton(cur && cur.in_jail ? '🎲 Coba Keluar Penjara' : '🎲 Lempar Dadu', 'btn-primary', () => sendAction('roll'));
        if (has('pay_jail')) actionButton('💰 Bayar Denda', 'btn-accent', () => sendAction('pay_jail'));
        if (has('use_jail_card')) actionButton('🎫 Pakai Kartu Bebas', 'btn-outline', () => sendAction('use_jail_card'));

        if (has('buy')) {
            const price = room.pending ? room.pending.price : 0;
            actionButton(`🏷️ Beli ${money(price)}`, 'btn-accent', () => sendAction('buy'),
                { disabled: !state.can_buy, title: state.can_buy ? '' : 'Uang tidak cukup' });
        }
        if (has('decline')) actionButton('Lewati', 'btn-outline', () => sendAction('decline'));

        if (has('build') || has('mortgage') || has('sell_building') || has('unmortgage')) {
            actionButton('🏘️ Kelola Properti', 'btn-outline', openManage);
        }
        if (has('end_turn')) actionButton('Akhiri Giliran ➜', 'btn-primary', () => sendAction('end_turn'));

        if (has('pay_debt')) actionButton('💳 Bayar Utang', 'btn-accent', () => sendAction('pay_debt'));
        if (has('declare_bankruptcy')) {
            actionButton('💀 Bangkrut', 'btn-danger', () => openModal({
                title: 'Menyatakan bangkrut?',
                body: 'Semua aset Anda akan dilepas dan Anda keluar dari permainan. Tindakan ini tidak bisa dibatalkan.',
                buttons: [
                    { label: 'Batal', cls: 'btn-outline' },
                    { label: 'Ya, bangkrut', cls: 'btn-danger', onClick: () => sendAction('declare_bankruptcy') },
                ],
            }));
        }
    }

    // ====================================================== property modals
    // array atau objek berkunci (mis. {1: 25, 2: 50}) → [[kunci, nilai], ...]
    const entries = o => Array.isArray(o) ? o.map((v, i) => [i + 1, v]) : Object.entries(o || {});

    function rentTable(t) {
        const wrap = el('div');
        const add = (k, v, hl) => {
            const r = el('div', 'kv' + (hl ? ' hl' : ''));
            r.appendChild(el('span', '', k));
            r.appendChild(el('span', '', v));
            wrap.appendChild(r);
        };
        if (t.type === 'property' && Array.isArray(t.rent)) {
            const prop = propOf(t.id);
            const lvl = prop ? (prop.has_hotel ? 5 : prop.houses || 0) : -1;
            const labels = ['Sewa dasar', '1 rumah', '2 rumah', '3 rumah', '4 rumah', 'Hotel'];
            t.rent.forEach((r, i) => add(labels[i] || ('Level ' + i), money(r), i === lvl));
            const g = GROUPS[t.group];
            if (g) add('Biaya bangun / rumah', money(g.house_cost));
        } else if (t.type === 'station' && config.stationRent) {
            entries(config.stationRent).forEach(([n, r]) => add(`${n} stasiun dimiliki`, money(r)));
        } else if (t.type === 'utility' && config.utilityMultiplier) {
            entries(config.utilityMultiplier).forEach(([n, m]) => add(`${n} utilitas dimiliki`, `dadu × ${m}`));
        }
        return wrap;
    }

    function showTileModal(id) {
        const t = tileById(id);
        if (!t) return;
        const prop = propOf(id);
        const owner = prop && prop.owner_player_id ? playerById(prop.owner_player_id) : null;
        const cur = currentPlayer();
        const group = t.group && GROUPS[t.group];

        const body = el('div');
        const add = (k, v) => {
            const r = el('div', 'kv');
            r.appendChild(el('span', '', k));
            r.appendChild(el('span', '', v));
            body.appendChild(r);
        };
        const kind = { property: 'Properti', station: 'Stasiun', utility: 'Utilitas', tax: 'Pajak', chance: 'Kesempatan',
            chest: 'Dana Umum', start: 'Start', jail: 'Penjara', free_parking: 'Parkir Bebas', go_to_jail: 'Masuk Penjara' }[t.type] || t.type;
        add('Jenis', kind);
        if (t.price) add('Harga', money(t.price));
        if (t.amount) add('Pajak', money(t.amount));

        if (t.price) {
            add('Pemilik', owner ? owner.name : 'Belum dimiliki');
            if (prop) {
                if (prop.is_mortgaged) add('Status', 'Digadaikan');
                if (prop.has_hotel) add('Bangunan', '🏨 Hotel');
                else if (prop.houses > 0) add('Bangunan', '🏠 × ' + prop.houses);
            }
            body.appendChild(el('div', 'panel-title', 'Tabel sewa'));
            body.appendChild(rentTable(t));
        }

        const buttons = [{ label: 'Tutup', cls: 'btn-outline' }];
        const mine = owner && cur && owner.id === cur.id && state.can_act;
        if (mine && prop) {
            const a = state.actions || [];
            const run = act => async () => {
                const ok = await sendAction(act, { tile_id: id });
                if (ok) showTileModal(id);
            };
            if (t.type === 'property' && a.includes('build') && !prop.is_mortgaged && !prop.has_hotel) {
                buttons.unshift({ label: '🏠 Bangun', cls: 'btn-primary', keepOpen: true, onClick: run('build') });
            }
            if (t.type === 'property' && a.includes('sell_building') && (prop.houses > 0 || prop.has_hotel)) {
                buttons.unshift({ label: '🔨 Jual bangunan', cls: 'btn-outline', keepOpen: true, onClick: run('sell_building') });
            }
            if (!prop.is_mortgaged && a.includes('mortgage')) {
                buttons.unshift({ label: '📉 Gadai', cls: 'btn-outline', keepOpen: true, onClick: run('mortgage') });
            }
            if (prop.is_mortgaged && a.includes('unmortgage')) {
                buttons.unshift({ label: '📈 Tebus', cls: 'btn-accent', keepOpen: true, onClick: run('unmortgage') });
            }
        }
        openModal({ title: t.name, body, buttons, color: group ? group.color : null });
    }

    function openManage() {
        const cur = currentPlayer();
        if (!cur) return;
        const mine = state.properties.filter(p => p.owner_player_id === cur.id).sort((a, b) => a.tile_id - b.tile_id);
        const body = el('div');
        if (!mine.length) body.appendChild(el('p', '', 'Anda belum memiliki properti.'));
        mine.forEach(p => {
            const t = tileById(p.tile_id);
            if (!t) return;
            const row = el('div', 'manage-row');
            const g = t.group && GROUPS[t.group];
            const sw = el('span', 'dot');
            sw.style.background = g ? g.color : '#999';
            row.appendChild(sw);
            row.appendChild(el('span', 'nm', t.name));
            if (p.is_mortgaged) row.appendChild(el('span', 'badge warn', 'Gadai'));
            if (p.has_hotel) row.appendChild(el('span', 'badge', '🏨'));
            else if (p.houses) row.appendChild(el('span', 'badge', '🏠 ' + p.houses));
            const b = el('button', 'btn btn-outline btn-sm', 'Kelola');
            b.addEventListener('click', () => showTileModal(p.tile_id));
            row.appendChild(b);
            body.appendChild(row);
        });
        openModal({ title: 'Kelola Properti', body, buttons: [{ label: 'Tutup', cls: 'btn-outline' }] });
    }

    // ====================================================== network
    function applyState(data, force) {
        if (!data || typeof data.version !== 'number' || !data.room) return false;
        if (data.version < version) return false;
        if (data.version === version && !force) return false;
        state = data;
        version = data.version;
        render();
        maybeRunAi();
        return true;
    }

    async function fetchState(force) {
        try {
            const res = await fetch(config.urls.state, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            applyState(await res.json(), force);
        } catch (e) { /* abaikan, coba lagi pada polling berikutnya */ }
    }
    window.refreshGameState = () => fetchState(true);

    async function sendAction(action, params) {
        if (busy) return false;
        busy = true;
        document.querySelectorAll('#action-bar .btn').forEach(b => b.disabled = true);
        try {
            const res = await fetch(config.urls.action.replace('__ACTION__', action), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Idempotency-Key': uuid(),
                },
                body: JSON.stringify({ ...(params || {}), expected_version: version }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                applyState(data, true);
                return true;
            }
            const msg = data.message || (res.status === 429 ? 'Terlalu cepat, coba sebentar lagi.' : `Terjadi kesalahan (${res.status}).`);
            toast(msg, 'error');
            if (res.status === 422 || res.status === 409) await fetchState(true);
            return false;
        } catch (e) {
            toast('Gagal terhubung ke server.', 'error');
            return false;
        } finally {
            busy = false;
            updateActions();
        }
    }

    // ---- giliran komputer
    function maybeRunAi() {
        if (aiBusy || !state.ai_turn || state.room.status !== 'playing') return;
        aiBusy = true;
        setTimeout(async () => {
            try {
                const res = await fetch(config.urls.aiStep, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ expected_version: version }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) applyState(data, true);
                else await fetchState(true);
            } catch (e) {
                /* jaringan putus: polling akan memicu ulang */
            } finally {
                aiBusy = false;
                if (state.ai_turn) setTimeout(maybeRunAi, 400);
            }
        }, 900);
    }

    // ---- real-time + polling
    function setConn(mode) {
        const c = $('conn');
        c.className = 'conn ' + mode;
        c.lastElementChild.textContent = mode === 'live' ? 'Live' : 'Polling';
    }

    function connectRealtime() {
        setConn('poll');
        if (typeof window.Echo === 'undefined') return;
        try {
            window.Echo.private('game.' + ROOM_CODE)
                .listen('GameStateChanged', e => {
                    // Snapshot yang di-broadcast dibuat untuk host (aksi tombol milik host),
                    // jadi selalu ambil snapshot pribadi dari server.
                    if (e && e.version > version) fetchState(true);
                })
                .error(() => setConn('poll'));
            const conn = window.Echo.connector && window.Echo.connector.pusher && window.Echo.connector.pusher.connection;
            if (conn) {
                conn.bind('connected', () => setConn('live'));
                conn.bind('disconnected', () => setConn('poll'));
                conn.bind('unavailable', () => setConn('poll'));
                if (conn.state === 'connected') setConn('live');
            }
        } catch (e) { setConn('poll'); }
    }

    // ====================================================== render
    function render() {
        updateHeader();
        updateBoard();
        updateCenter();
        updatePlayers();
        updateLog();
        updateActions();
        checkWinner();
    }

    function checkWinner() {
        if (state.room.status !== 'finished' || winnerShown) return;
        winnerShown = true;
        const w = playerById(state.room.winner_player_id);
        const body = el('div');
        body.appendChild(el('div', 'winner', '🏆'));
        const p = el('p', '', w ? `${w.name} memenangkan permainan dengan ${money(w.money)}!` : 'Permainan telah selesai.');
        p.style.textAlign = 'center';
        body.appendChild(p);
        openModal({
            title: 'Permainan selesai',
            body,
            buttons: [
                { label: 'Lihat papan', cls: 'btn-outline' },
                { label: 'Kembali ke menu', cls: 'btn-primary', onClick: () => { location.href = config.urls.menu; } },
            ],
        });
    }

    // ====================================================== init
    document.querySelectorAll('.side-tabs button').forEach(b => {
        b.addEventListener('click', () => {
            $('sidebar').dataset.tab = b.dataset.tab;
            document.querySelectorAll('.side-tabs button').forEach(x => x.classList.toggle('on', x === b));
        });
    });
    $('btn-exit').addEventListener('click', () => { location.href = config.urls.menu; });
    $('modal-overlay').addEventListener('click', e => { if (e.target === $('modal-overlay')) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modalOpen()) closeModal(); });

    renderBoard();
    render();
    connectRealtime();
    maybeRunAi();
    setInterval(() => { if (!document.hidden && !busy) fetchState(false); }, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) fetchState(false); });
})();
</script>
</body>
</html>