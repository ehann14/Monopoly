@php
    $players = $room->players()->get();
@endphp

<div id="gmPanel" style="display:none; position:fixed; top:0; right:0; width:350px; height:100vh; background:#fff; box-shadow:-2px 0 10px rgba(0,0,0,0.2); z-index:9999; overflow-y:auto; padding:20px; font-family:system-ui;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0; color:#0f3d2e;">🎮 Game Master</h2>
        <button onclick="document.getElementById('gmPanel').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>

    <!-- Tab Navigasi -->
    <div style="display:flex; gap:5px; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
        <button onclick="showGmTab('money')" class="gm-tab-btn" style="flex:1; padding:5px; border:1px solid #ddd; background:#f8f9fa; cursor:pointer;">Uang</button>
        <button onclick="showGmTab('dice')" class="gm-tab-btn" style="flex:1; padding:5px; border:1px solid #ddd; background:#f8f9fa; cursor:pointer;">Dadu</button>
        <button onclick="showGmTab('pos')" class="gm-tab-btn" style="flex:1; padding:5px; border:1px solid #ddd; background:#f8f9fa; cursor:pointer;">Posisi</button>
    </div>

    <!-- Tab Uang -->
    <div id="gm-tab-money" class="gm-tab-content">
        <h3>Atur Uang</h3>
        <form onsubmit="submitGmAction(event, 'money')">
            <label>Pemain:</label>
            <select name="player_id" style="width:100%; padding:5px; margin-bottom:10px;">
                @foreach($players as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->money }})</option>
                @endforeach
            </select>
            
            <label>Aksi:</label>
            <select name="action" style="width:100%; padding:5px; margin-bottom:10px;">
                <option value="add">Tambah</option>
                <option value="subtract">Kurangi</option>
                <option value="set">Set Menjadi</option>
            </select>
            
            <label>Jumlah:</label>
            <input type="number" name="amount" min="0" style="width:100%; padding:5px; margin-bottom:10px;">
            
            <button type="submit" style="width:100%; padding:8px; background:#0f3d2e; color:#fff; border:none; border-radius:4px; cursor:pointer;">Eksekusi</button>
        </form>
    </div>

    <!-- Tab Dadu -->
    <div id="gm-tab-dice" class="gm-tab-content" style="display:none;">
        <h3>Atur Dadu</h3>
        <form onsubmit="submitGmAction(event, 'dice')">
            <label>Dadu 1:</label>
            <input type="number" name="die1" min="1" max="6" value="6" style="width:100%; padding:5px; margin-bottom:10px;">
            
            <label>Dadu 2:</label>
            <input type="number" name="die2" min="1" max="6" value="6" style="width:100%; padding:5px; margin-bottom:10px;">
            
            <label>Jumlah Penggunaan:</label>
            <input type="number" name="uses" min="1" value="1" style="width:100%; padding:5px; margin-bottom:10px;">
            
            <label>Target (Opsional):</label>
            <select name="target_player_id" style="width:100%; padding:5px; margin-bottom:10px;">
                <option value="">Semua Pemain</option>
                @foreach($players as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            
            <button type="submit" style="width:100%; padding:8px; background:#0f3d2e; color:#fff; border:none; border-radius:4px; cursor:pointer;">Set Dadu</button>
        </form>
    </div>

    <!-- Tab Posisi -->
    <div id="gm-tab-pos" class="gm-tab-content" style="display:none;">
        <h3>Pindahkan Bidak</h3>
        <form onsubmit="submitGmAction(event, 'move')">
            <label>Pemain:</label>
            <select name="player_id" style="width:100%; padding:5px; margin-bottom:10px;">
                @foreach($players as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            
            <label>Petak Tujuan (0-39):</label>
            <input type="number" name="tile_id" min="0" max="39" style="width:100%; padding:5px; margin-bottom:10px;">
            
            <label style="display:flex; align-items:center; gap:5px; margin-bottom:10px;">
                <input type="checkbox" name="trigger_effect" value="1"> Jalankan Efek Petak
            </label>
            
            <button type="submit" style="width:100%; padding:8px; background:#0f3d2e; color:#fff; border:none; border-radius:4px; cursor:pointer;">Pindahkan</button>
        </form>
    </div>
    
    <div style="margin-top:20px; padding-top:10px; border-top:1px solid #eee;">
        <button onclick="toggleChaos()" style="width:100%; padding:8px; background:#dc3545; color:#fff; border:none; border-radius:4px; cursor:pointer;">Toggle Chaos Mode</button>
    </div>
</div>

<script>
function showGmTab(tabName) {
    document.querySelectorAll('.gm-tab-content').forEach(el => el.style.display = 'none');
    document.getElementById('gm-tab-' + tabName).style.display = 'block';
}

async function submitGmAction(e, type) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const code = "{{ $room->code }}";
    
    let url = `/gm/${code}/`;
    if (type === 'money') url += 'money';
    if (type === 'dice') url += 'dice';
    if (type === 'move') url += 'move';

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(Object.fromEntries(data))
        });
        
        if (response.ok) {
            alert('Berhasil!');
            location.reload(); // Refresh untuk update state
        } else {
            const err = await response.json();
            alert('Gagal: ' + JSON.stringify(err));
        }
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan jaringan.');
    }
}

async function toggleChaos() {
    const code = "{{ $room->code }}";
    try {
        await fetch(`/gm/${code}/chaos`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ enabled: true }) // Bisa ditambah logic toggle
        });
        alert('Chaos mode toggled');
    } catch (e) { console.error(e); }
}
</script>