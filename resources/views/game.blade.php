<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Monopoly — {{ $room->code }}</title>
<style>
:root{--bg:#0f3d2e;--paper:#fbf6e6;--ink:#1d2b24;--accent:#f2c300}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
.top{display:flex;align-items:center;gap:12px;padding:10px 16px;background:#0a2b20;color:#fff;font-size:14px}
.top a{color:var(--accent);text-decoration:none}
.conn{margin-left:auto;color:#6bdc7c;font-size:13px}.conn.bad{color:#ff6b6b}
#gmflag{background:#7a1fa2;padding:2px 10px;border-radius:10px;font-size:12px}
.layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px;padding:16px;max-width:1280px;margin:0 auto}
.board{display:grid;grid-template-columns:1.5fr repeat(9,1fr) 1.5fr;grid-template-rows:1.5fr repeat(9,1fr) 1.5fr;aspect-ratio:1/1;width:100%;max-width:820px;margin:0 auto;gap:2px;background:#2b5a46;padding:2px;border-radius:12px}
.tile{position:relative;background:var(--paper);display:flex;flex-direction:column;overflow:hidden;cursor:pointer;min-width:0;min-height:0;border-radius:3px;font-size:clamp(6px,1.05vw,11px);line-height:1.1;text-align:center}
.tile:focus-visible{outline:2px solid var(--accent)}
.tile.sel{outline:2px solid #1e88e5;z-index:2}
.band{height:16%;min-height:4px;flex:none}.band.noband{background:transparent}
.tname{padding:2px;font-weight:600;flex:1;display:flex;align-items:center;justify-content:center;overflow:hidden;word-break:break-word}
.tprice{font-size:.9em;color:#555;padding-bottom:2px}
.mark{display:flex;gap:2px;justify-content:center;align-items:center;min-height:.9em;margin-bottom:1.9em}
.ownerdot{width:.9em;height:.9em;border-radius:50%;border:1px solid #333}
.house{width:.7em;height:.7em;background:#2e7d32}.hotel{width:1.2em;height:.8em;background:#c62828}
.tile.mortgaged{filter:grayscale(1);opacity:.6}
.tokens{position:absolute;left:0;right:0;bottom:1px;display:flex;justify-content:center;flex-wrap:wrap;gap:1px}
.token{width:1.9em;height:1.9em;border-radius:50%;border:1px solid #222;font-size:.9em;display:grid;place-items:center;color:#111;font-weight:700}
.token.active{outline:2px solid #fff;box-shadow:0 0 0 3px #111}
.t-start,.t-jail,.t-free_parking,.t-go_to_jail{background:#e8dfc0;font-weight:700}
.t-chance{background:#ffe0b2}.t-chest{background:#c8e6c9}.t-tax{background:#ffcdd2}
.center{grid-row:2/11;grid-column:2/11;background:#cfe8d5;border-radius:6px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:8px;text-align:center}
.logo{font-weight:900;letter-spacing:.2em;font-size:clamp(14px,3.5vw,34px);color:#b71c1c}
.turn{font-weight:600;font-size:clamp(11px,1.8vw,18px)}
.dot{display:inline-block;width:12px;height:12px;border-radius:50%;border:1px solid #222;vertical-align:middle}
.dice{display:flex;gap:10px}
.die{width:clamp(28px,6vw,56px);aspect-ratio:1;background:#fff;border:2px solid #222;border-radius:10px;display:grid;place-items:center;font-size:clamp(16px,3.5vw,30px);font-weight:800}
.dice.roll .die{animation:shake .5s}
@keyframes shake{0%{transform:rotate(0) scale(.8)}30%{transform:rotate(-25deg) scale(1.15)}60%{transform:rotate(20deg) scale(1.05)}100%{transform:rotate(0) scale(1)}}
.panel{background:#fff;border-radius:14px;padding:14px;margin-bottom:12px}
.panel h3{margin:0 0 8px;font-size:15px}
.muted{color:#667;font-size:13px;margin:4px 0}
.btn{display:block;width:100%;padding:12px;margin-top:8px;border:0;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;background:var(--bg);color:#fff}
.btn.alt{background:#e0e0e0;color:#222}.btn.gold{background:var(--accent);color:#222}
.btn:disabled{opacity:.5;cursor:not-allowed}.btn[hidden]{display:none}
.pcard{border:2px solid #eee;border-radius:10px;padding:8px;margin-bottom:8px}
.pcard.current{border-color:var(--accent);background:#fffbe6}.pcard.bankrupt{opacity:.5}
.phead{display:flex;align-items:center;gap:8px}.pmoney{margin-left:auto;font-weight:700}
.chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
.chip{font-size:11px;padding:2px 6px;border-radius:8px;background:#ddd;border-left:6px solid #999}
.dname{font-weight:800;font-size:17px;margin-bottom:6px}
.row{display:flex;justify-content:space-between;font-size:13px;padding:2px 0;border-bottom:1px dashed #eee}
#log{list-style:none;margin:0;padding:0;max-height:220px;overflow:auto;font-size:13px}
#log li{padding:3px 0;border-bottom:1px solid #f0f0f0}
#toast{position:fixed;left:50%;top:14px;transform:translateX(-50%);background:#b00020;color:#fff;padding:10px 16px;border-radius:10px;font-size:14px;z-index:50;display:none;max-width:90vw}
details.panel summary{cursor:pointer;font-weight:700}
details.panel p{font-size:13px;margin:6px 0}
@media (max-width:980px){
  .layout{grid-template-columns:1fr;padding:8px}
  body{padding-bottom:190px}
  .actions{position:fixed;left:0;right:0;bottom:0;z-index:20;margin:0;border-radius:14px 14px 0 0;box-shadow:0 -4px 14px rgba(0,0,0,.3)}
}
</style>
</head>
<body>
<header class="top">
    <a href="{{ route('menu') }}">&larr; Menu</a>
    <strong>Room {{ $room->code }}</strong>
    <span id="gmflag" hidden>Game Master Mode aktif</span>
    <span id="conn" class="conn">&#9679; terhubung</span>
</header>

<main class="layout">
    <section><div id="board" class="board"></div></section>

    <aside>
        <section class="panel actions" id="actions">
            <h3>Aksi</h3>
            <p class="muted" id="hint"></p>
            <button class="btn gold" id="btnRoll" type="button" hidden>Lempar dadu</button>
            <button class="btn" id="btnBuy" type="button" hidden>Beli</button>
            <button class="btn alt" id="btnDecline" type="button" hidden>Tidak membeli</button>
            <button class="btn" id="btnEnd" type="button" hidden>Akhiri giliran</button>
        </section>

        <section class="panel"><h3>Pemain</h3><div id="players"></div></section>
        <section class="panel" id="detail"></section>
        <section class="panel"><h3>Log permainan</h3><ul id="log"></ul></section>

        <details class="panel">
            <summary>Bantuan aturan</summary>
            <p>Lempar dua dadu dan bergerak searah jarum jam. Melewati START memberi gaji.</p>
            <p>Petak properti yang belum dimiliki bisa dibeli. Jika menolak, properti tetap milik bank (lelang belum tersedia).</p>
            <p>Mendarat di properti orang lain berarti membayar sewa. Memiliki seluruh kelompok warna menggandakan sewa dasar.</p>
            <p>Dadu kembar memberi giliran tambahan. Dadu kembar 3 kali berturut-turut mengakhiri giliran tanpa bergerak.</p>
            <p>Pemain yang tidak mampu membayar langsung bangkrut. Pemain terakhir yang tersisa menang.</p>
            <p>Belum aktif: rumah/hotel, hipotek, penjara, kartu (Tahap 3), AI (Tahap 4), online (Tahap 5).</p>
        </details>
    </aside>
</main>
<div id="toast" role="alert"></div>

<script>
(function () {
'use strict';
const DATA = @json($clientData);
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const COLORS = {merah:'#e53935', biru:'#1e88e5', hijau:'#43a047', kuning:'#fdd835'};
const TYPE_NAMES = {start:'START', property:'Properti', station:'Stasiun', utility:'Utilitas', tax:'Pajak',
    chance:'Kesempatan', chest:'Dana Umum', jail:'Penjara / hanya berkunjung', free_parking:'Parkir Bebas', go_to_jail:'Masuk Penjara'};
const RENT_LABELS = ['Sewa dasar', 'Dengan 1 rumah', 'Dengan 2 rumah', 'Dengan 3 rumah', 'Dengan 4 rumah', 'Dengan hotel'];

let state = DATA.snapshot;
let busy = false;
let selectedTile = null;
let lastDiceKey = null;
const tileEls = [];

const $ = (id) => document.getElementById(id);
function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined && text !== null) e.textContent = text;
    return e;
}
function money(n) { return 'M ' + new Intl.NumberFormat('id-ID').format(n); }
function playerById(id) { return state.players.find((p) => p.id === id) || null; }

// Posisi petak pada grid 11x11 (baris, kolom), mulai dari pojok kanan bawah (START) berlawanan jarum jam.
function gridPos(id) {
    if (id === 0) return [11, 11];
    if (id < 10) return [11, 11 - id];
    if (id === 10) return [11, 1];
    if (id < 20) return [11 - (id - 10), 1];
    if (id === 20) return [1, 1];
    if (id < 30) return [1, 1 + (id - 20)];
    if (id === 30) return [1, 11];
    return [1 + (id - 30), 11];
}

function buildBoard() {
    const board = $('board');
    DATA.tiles.forEach((t) => {
        const pos = gridPos(t.id);
        const d = el('div', 'tile t-' + t.type);
        d.setAttribute('role', 'button');
        d.tabIndex = 0;
        d.setAttribute('aria-label', t.name);
        d.style.gridRow = String(pos[0]);
        d.style.gridColumn = String(pos[1]);

        const band = el('div', 'band');
        if (t.group) { band.style.background = DATA.groups[t.group].color; } else { band.classList.add('noband'); }
        d.appendChild(band);
        d.appendChild(el('div', 'tname', t.name));
        if (t.price) d.appendChild(el('div', 'tprice', String(t.price)));
        else if (t.amount) d.appendChild(el('div', 'tprice', '-' + t.amount));

        const mark = el('div', 'mark');
        const tokens = el('div', 'tokens');
        d.appendChild(mark);
        d.appendChild(tokens);

        const select = () => { selectedTile = t.id; renderDetail(); renderSelection(); };
        d.addEventListener('click', select);
        d.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(); } });

        board.appendChild(d);
        tileEls[t.id] = {root: d, mark: mark, tokens: tokens};
    });
    const center = el('div', 'center');
    center.id = 'center';
    board.appendChild(center);
}

function renderSelection() {
    tileEls.forEach((r, i) => r.root.classList.toggle('sel', i === selectedTile));
}

function renderBoard() {
    DATA.tiles.forEach((t) => {
        const refs = tileEls[t.id];
        refs.tokens.replaceChildren();
        state.players.filter((p) => !p.is_bankrupt && p.position === t.id).forEach((p) => {
            const tk = el('span', 'token', p.name.charAt(0).toUpperCase());
            tk.style.background = COLORS[p.token] || '#999';
            tk.title = p.name;
            if (p.id === state.current_player_id && state.room.status === 'playing') tk.classList.add('active');
            refs.tokens.appendChild(tk);
        });

        refs.mark.replaceChildren();
        const pr = state.properties[t.id];
        refs.root.classList.toggle('mortgaged', !!(pr && pr.mortgaged));
        if (pr && pr.owner !== null) {
            const owner = playerById(pr.owner);
            const dot = el('span', 'ownerdot');
            dot.style.background = owner ? (COLORS[owner.token] || '#999') : '#999';
            refs.mark.appendChild(dot);
            if (pr.hotel) {
                refs.mark.appendChild(el('span', 'hotel'));
            } else {
                for (let i = 0; i < pr.houses; i++) refs.mark.appendChild(el('span', 'house'));
            }
            if (pr.mortgaged) refs.mark.appendChild(el('span', null, 'M'));
        }
    });
}

function renderCenter() {
    const c = $('center');
    c.replaceChildren();
    c.appendChild(el('div', 'logo', 'MONOPOLY'));

    const cur = playerById(state.current_player_id);
    const turn = el('div', 'turn');
    if (state.room.status === 'finished') {
        const w = playerById(state.winner_id);
        turn.textContent = 'Selesai! Pemenang: ' + (w ? w.name : '-');
    } else if (cur) {
        const dot = el('span', 'dot');
        dot.style.background = COLORS[cur.token] || '#999';
        turn.appendChild(dot);
        turn.appendChild(document.createTextNode(' Giliran ' + cur.name));
    }
    c.appendChild(turn);

    const dice = el('div', 'dice');
    const ld = state.last_dice;
    dice.appendChild(el('div', 'die', ld ? String(ld.d1) : '?'));
    dice.appendChild(el('div', 'die', ld ? String(ld.d2) : '?'));
    const diceLogs = state.logs.filter((l) => l.type === 'dice');
    const key = diceLogs.length ? diceLogs[diceLogs.length - 1].id : 0;
    if (lastDiceKey !== null && key !== lastDiceKey) dice.classList.add('roll');
    lastDiceKey = key;
    c.appendChild(dice);
}

function renderActions() {
    const a = state.actions;
    const map = {roll: 'btnRoll', buy: 'btnBuy', decline: 'btnDecline', end_turn: 'btnEnd'};
    Object.keys(map).forEach((act) => {
        const b = $(map[act]);
        b.hidden = !a.includes(act);
        b.disabled = busy;
    });

    const cur = playerById(state.current_player_id);
    let hint = '';
    if (state.room.status === 'finished') {
        hint = 'Permainan selesai.';
    } else if (!state.can_act) {
        hint = cur ? 'Menunggu giliran ' + cur.name + '.' : '';
    } else if (state.phase === 'await_roll') {
        hint = 'Giliran ' + cur.name + ': lempar dadu.';
    } else if (state.phase === 'await_buy' && state.pending) {
        const t = DATA.tiles[state.pending.tile_id];
        hint = t.name + ' belum dimiliki. Harga ' + money(state.pending.price) + ', saldo ' + money(cur.money) + '.';
        $('btnBuy').textContent = 'Beli ' + t.name + ' (' + money(state.pending.price) + ')';
        $('btnBuy').disabled = busy || !state.can_buy;
        if (!state.can_buy) hint += ' Uang tidak cukup untuk membeli.';
    } else if (state.phase === 'await_end') {
        hint = 'Selesaikan giliran Anda.';
    }
    $('hint').textContent = hint;
}

function renderPlayers() {
    const box = $('players');
    box.replaceChildren();
    state.players.forEach((p) => {
        const card = el('div', 'pcard');
        if (p.id === state.current_player_id && state.room.status === 'playing') card.classList.add('current');
        if (p.is_bankrupt) card.classList.add('bankrupt');

        const head = el('div', 'phead');
        const dot = el('span', 'dot');
        dot.style.background = COLORS[p.token] || '#999';
        head.appendChild(dot);
        head.appendChild(el('strong', null, p.name));
        head.appendChild(el('span', 'pmoney', p.is_bankrupt ? 'Bangkrut' : money(p.money)));
        card.appendChild(head);

        const chips = el('div', 'chips');
        Object.keys(state.properties).forEach((tileId) => {
            const pr = state.properties[tileId];
            if (pr.owner !== p.id) return;
            const t = DATA.tiles[tileId];
            let label = t.name;
            if (pr.hotel) label += ' (hotel)';
            else if (pr.houses > 0) label += ' (' + pr.houses + ' rumah)';
            if (pr.mortgaged) label += ' [gadai]';
            const chip = el('span', 'chip', label);
            chip.style.borderLeftColor = t.group ? DATA.groups[t.group].color : '#777';
            chips.appendChild(chip);
        });
        if (chips.childNodes.length) card.appendChild(chips);
        box.appendChild(card);
    });
}

function row(label, value) {
    const r = el('div', 'row');
    r.appendChild(el('span', null, label));
    r.appendChild(el('strong', null, value));
    return r;
}

function renderDetail() {
    const box = $('detail');
    box.replaceChildren();
    box.appendChild(el('h3', null, 'Detail petak'));
    if (selectedTile === null) {
        box.appendChild(el('p', 'muted', 'Ketuk petak di papan untuk melihat detailnya.'));
        return;
    }
    const t = DATA.tiles[selectedTile];
    box.appendChild(el('div', 'dname', t.name));
    box.appendChild(row('Jenis', TYPE_NAMES[t.type] || t.type));
    if (t.group) box.appendChild(row('Kelompok', t.group));
    if (t.price) {
        box.appendChild(row('Harga beli', money(t.price)));
        box.appendChild(row('Nilai hipotek', money(Math.floor(t.price / 2))));
    }
    if (t.amount) box.appendChild(row('Pajak', money(t.amount)));
    if (t.type === 'property') {
        t.rent.forEach((r, i) => box.appendChild(row(RENT_LABELS[i], money(r))));
        box.appendChild(row('Harga rumah / hotel', money(DATA.groups[t.group].house_cost)));
    }
    if (t.type === 'station') {
        Object.keys(DATA.stationRent).forEach((n) => box.appendChild(row('Sewa (' + n + ' stasiun)', money(DATA.stationRent[n]))));
    }
    if (t.type === 'utility') {
        Object.keys(DATA.utilityMultiplier).forEach((n) => box.appendChild(row('Sewa (' + n + ' utilitas)', DATA.utilityMultiplier[n] + ' x dadu')));
    }
    const pr = state.properties[t.id];
    if (pr) {
        const owner = pr.owner !== null ? playerById(pr.owner) : null;
        box.appendChild(row('Pemilik', owner ? owner.name : 'Bank'));
        if (t.type === 'property') box.appendChild(row('Bangunan', pr.hotel ? 'Hotel' : pr.houses + ' rumah'));
        box.appendChild(row('Status', pr.mortgaged ? 'Digadaikan' : 'Aktif'));
    }
}

function renderLog() {
    const ul = $('log');
    const nearBottom = ul.scrollHeight - ul.scrollTop - ul.clientHeight < 40;
    ul.replaceChildren();
    state.logs.forEach((l) => ul.appendChild(el('li', null, l.message)));
    if (nearBottom) ul.scrollTop = ul.scrollHeight;
}

function render() {
    $('gmflag').hidden = !state.room.gm_enabled;
    renderBoard();
    renderCenter();
    renderActions();
    renderPlayers();
    renderDetail();
    renderSelection();
    renderLog();
}

function apply(next) {
    if (next.version < state.version) return;
    state = next;
    render();
}

let toastTimer = null;
function toast(msg) {
    const t = $('toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.style.display = 'none'; }, 3500);
}

function setConn(ok) {
    const c = $('conn');
    c.textContent = ok ? '\u25CF terhubung' : '\u25CF terputus';
    c.classList.toggle('bad', !ok);
}

function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

async function refresh() {
    try {
        const r = await fetch(DATA.urls.state, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
        if (!r.ok) throw new Error('http ' + r.status);
        apply(await r.json());
        setConn(true);
    } catch (e) {
        setConn(false);
    }
}

async function act(action) {
    if (busy) return;
    busy = true;
    renderActions();
    try {
        const r = await fetch(DATA.urls.action.replace('__ACTION__', action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Idempotency-Key': uuid(),
            },
            body: JSON.stringify({expected_version: state.version}),
        });
        let body = {};
        try { body = await r.json(); } catch (e) { body = {}; }
        if (r.ok) {
            apply(body);
            setConn(true);
        } else {
            toast(r.status === 419 ? 'Sesi berakhir. Muat ulang halaman.' : (body.message || 'Aksi gagal.'));
            await refresh();
        }
    } catch (e) {
        toast('Koneksi bermasalah.');
        setConn(false);
    } finally {
        busy = false;
        renderActions();
    }
}

$('btnRoll').addEventListener('click', () => act('roll'));
$('btnBuy').addEventListener('click', () => act('buy'));
$('btnDecline').addEventListener('click', () => act('decline'));
$('btnEnd').addEventListener('click', () => act('end_turn'));

buildBoard();
render();
setInterval(() => { if (!document.hidden && !busy) refresh(); }, 2500);
})();
</script>
</body>
</html>