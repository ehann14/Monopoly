<?php

/**
 * Definisi kartu. Jenis efek:
 *  money{amount}  move_to{tile}  move_back{steps}  nearest{kind: station|utility}
 *  go_to_jail  jail_card  pay_each{amount}  collect_each{amount}  repairs{house,hotel}
 */
return [
    'chance' => [
        ['id' => 'ch_start', 'title' => 'Maju ke START', 'text' => 'Maju ke START dan terima gaji.', 'effect' => ['type' => 'move_to', 'tile' => 0]],
        ['id' => 'ch_jakarta', 'title' => 'Perjalanan ke Jakarta', 'text' => 'Maju ke Jakarta. Jika melewati START, terima gaji.', 'effect' => ['type' => 'move_to', 'tile' => 37]],
        ['id' => 'ch_palembang', 'title' => 'Wisata ke Palembang', 'text' => 'Maju ke Palembang. Jika melewati START, terima gaji.', 'effect' => ['type' => 'move_to', 'tile' => 11]],
        ['id' => 'ch_station', 'title' => 'Naik kereta', 'text' => 'Maju ke stasiun terdekat. Jika dimiliki pemain lain, bayar 2x sewa.', 'effect' => ['type' => 'nearest', 'kind' => 'station']],
        ['id' => 'ch_utility', 'title' => 'Tagihan layanan', 'text' => 'Maju ke utilitas terdekat. Jika dimiliki pemain lain, bayar 10x total dadu terakhir.', 'effect' => ['type' => 'nearest', 'kind' => 'utility']],
        ['id' => 'ch_dividen', 'title' => 'Dividen bank', 'text' => 'Bank membayar dividen 50.', 'effect' => ['type' => 'money', 'amount' => 50]],
        ['id' => 'ch_jailcard', 'title' => 'Bebas dari penjara', 'text' => 'Simpan kartu ini untuk keluar dari penjara.', 'effect' => ['type' => 'jail_card']],
        ['id' => 'ch_back3', 'title' => 'Mundur tiga petak', 'text' => 'Mundur 3 petak.', 'effect' => ['type' => 'move_back', 'steps' => 3]],
        ['id' => 'ch_jail', 'title' => 'Masuk penjara', 'text' => 'Langsung ke penjara tanpa menerima gaji.', 'effect' => ['type' => 'go_to_jail']],
        ['id' => 'ch_repairs', 'title' => 'Perbaikan bangunan', 'text' => 'Bayar 25 per rumah dan 100 per hotel milik Anda.', 'effect' => ['type' => 'repairs', 'house' => 25, 'hotel' => 100]],
        ['id' => 'ch_denda', 'title' => 'Denda lalu lintas', 'text' => 'Bayar denda 15.', 'effect' => ['type' => 'money', 'amount' => -15]],
        ['id' => 'ch_chairman', 'title' => 'Ketua dewan', 'text' => 'Bayar 50 kepada setiap pemain lain.', 'effect' => ['type' => 'pay_each', 'amount' => 50]],
    ],

    'chest' => [
        ['id' => 'cc_start', 'title' => 'Maju ke START', 'text' => 'Maju ke START dan terima gaji.', 'effect' => ['type' => 'move_to', 'tile' => 0]],
        ['id' => 'cc_bank', 'title' => 'Kesalahan bank', 'text' => 'Kesalahan bank menguntungkan Anda. Terima 200.', 'effect' => ['type' => 'money', 'amount' => 200]],
        ['id' => 'cc_dokter', 'title' => 'Biaya dokter', 'text' => 'Bayar biaya dokter 50.', 'effect' => ['type' => 'money', 'amount' => -50]],
        ['id' => 'cc_saham', 'title' => 'Jual saham', 'text' => 'Anda menjual saham. Terima 50.', 'effect' => ['type' => 'money', 'amount' => 50]],
        ['id' => 'cc_jailcard', 'title' => 'Bebas dari penjara', 'text' => 'Simpan kartu ini untuk keluar dari penjara.', 'effect' => ['type' => 'jail_card']],
        ['id' => 'cc_jail', 'title' => 'Masuk penjara', 'text' => 'Langsung ke penjara tanpa menerima gaji.', 'effect' => ['type' => 'go_to_jail']],
        ['id' => 'cc_liburan', 'title' => 'Dana liburan', 'text' => 'Dana liburan cair. Terima 100.', 'effect' => ['type' => 'money', 'amount' => 100]],
        ['id' => 'cc_pajak', 'title' => 'Pengembalian pajak', 'text' => 'Terima pengembalian pajak 20.', 'effect' => ['type' => 'money', 'amount' => 20]],
        ['id' => 'cc_ultah', 'title' => 'Hari ulang tahun', 'text' => 'Terima 10 dari setiap pemain (pemain yang uangnya kurang membayar sebisanya).', 'effect' => ['type' => 'collect_each', 'amount' => 10]],
        ['id' => 'cc_asuransi', 'title' => 'Premi asuransi', 'text' => 'Bayar premi asuransi 100.', 'effect' => ['type' => 'money', 'amount' => -100]],
        ['id' => 'cc_rs', 'title' => 'Biaya rumah sakit', 'text' => 'Bayar biaya rumah sakit 100.', 'effect' => ['type' => 'money', 'amount' => -100]],
        ['id' => 'cc_jalan', 'title' => 'Perbaikan jalan', 'text' => 'Bayar 40 per rumah dan 115 per hotel milik Anda.', 'effect' => ['type' => 'repairs', 'house' => 40, 'hotel' => 115]],
    ],
];