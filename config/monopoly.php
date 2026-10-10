<?php

return [
    'rules' => [
        'starting_money' => 1500,
        'salary' => 200,
        'jail_position' => 10,
        'jail_fine' => 50,
        'max_jail_turns' => 3,
        'max_doubles' => 3,            // dadu kembar ke-3 berturut-turut = penjara
        'house_stock' => 32,
        'hotel_stock' => 12,
        'free_parking_pot' => false,   // aturan sederhana: Free Parking tidak memberi apa-apa
        'double_rent_full_set' => true,// sewa dasar x2 bila satu kelompok lengkap & tanpa bangunan
        'auction_enabled' => false,    // lelang belum ada: UI tidak boleh menampilkan tombolnya
        'unmortgage_interest' => 0.10,
        'max_players' => 4,
        'min_players_multiplayer' => 2,
    ],

    'tokens' => ['merah', 'biru', 'hijau', 'kuning'],

    'groups' => [
        'brown'     => ['color' => '#8B5A2B', 'house_cost' => 50],
        'lightblue' => ['color' => '#87CEEB', 'house_cost' => 50],
        'pink'      => ['color' => '#D6489B', 'house_cost' => 100],
        'orange'    => ['color' => '#F28C28', 'house_cost' => 100],
        'red'       => ['color' => '#D32F2F', 'house_cost' => 150],
        'yellow'    => ['color' => '#F2C300', 'house_cost' => 150],
        'green'     => ['color' => '#2E8B57', 'house_cost' => 200],
        'blue'      => ['color' => '#1F4FBF', 'house_cost' => 200],
    ],
    // Biaya hotel = biaya rumah (dibayar sebagai ganti 4 rumah yang dikembalikan ke bank).

    'station_rent' => [1 => 25, 2 => 50, 3 => 100, 4 => 200],
    'utility_multiplier' => [1 => 4, 2 => 10],

    'board' => [
        ['id' => 0,  'name' => 'START',               'type' => 'start'],
        ['id' => 1,  'name' => 'Pontianak',           'type' => 'property', 'group' => 'brown', 'price' => 60,  'rent' => [2, 10, 30, 90, 160, 250]],
        ['id' => 2,  'name' => 'Dana Umum',           'type' => 'chest'],
        ['id' => 3,  'name' => 'Singkawang',          'type' => 'property', 'group' => 'brown', 'price' => 60,  'rent' => [4, 20, 60, 180, 320, 450]],
        ['id' => 4,  'name' => 'Pajak Penghasilan',   'type' => 'tax', 'amount' => 200],
        ['id' => 5,  'name' => 'Stasiun Gambir',      'type' => 'station', 'price' => 200],
        ['id' => 6,  'name' => 'Jambi',               'type' => 'property', 'group' => 'lightblue', 'price' => 100, 'rent' => [6, 30, 90, 270, 400, 550]],
        ['id' => 7,  'name' => 'Kesempatan',          'type' => 'chance'],
        ['id' => 8,  'name' => 'Pekanbaru',           'type' => 'property', 'group' => 'lightblue', 'price' => 100, 'rent' => [6, 30, 90, 270, 400, 550]],
        ['id' => 9,  'name' => 'Padang',              'type' => 'property', 'group' => 'lightblue', 'price' => 120, 'rent' => [8, 40, 100, 300, 450, 600]],
        ['id' => 10, 'name' => 'Penjara / Berkunjung','type' => 'jail'],
        ['id' => 11, 'name' => 'Palembang',           'type' => 'property', 'group' => 'pink', 'price' => 140, 'rent' => [10, 50, 150, 450, 625, 750]],
        ['id' => 12, 'name' => 'Listrik Nusantara',   'type' => 'utility', 'price' => 150],
        ['id' => 13, 'name' => 'Lampung',             'type' => 'property', 'group' => 'pink', 'price' => 140, 'rent' => [10, 50, 150, 450, 625, 750]],
        ['id' => 14, 'name' => 'Bengkulu',            'type' => 'property', 'group' => 'pink', 'price' => 160, 'rent' => [12, 60, 180, 500, 700, 900]],
        ['id' => 15, 'name' => 'Stasiun Senen',       'type' => 'station', 'price' => 200],
        ['id' => 16, 'name' => 'Semarang',            'type' => 'property', 'group' => 'orange', 'price' => 180, 'rent' => [14, 70, 200, 550, 750, 950]],
        ['id' => 17, 'name' => 'Dana Umum',           'type' => 'chest'],
        ['id' => 18, 'name' => 'Surabaya',            'type' => 'property', 'group' => 'orange', 'price' => 180, 'rent' => [14, 70, 200, 550, 750, 950]],
        ['id' => 19, 'name' => 'Malang',              'type' => 'property', 'group' => 'orange', 'price' => 200, 'rent' => [16, 80, 220, 600, 800, 1000]],
        ['id' => 20, 'name' => 'Parkir Bebas',        'type' => 'free_parking'],
        ['id' => 21, 'name' => 'Bandung',             'type' => 'property', 'group' => 'red', 'price' => 220, 'rent' => [18, 90, 250, 700, 875, 1050]],
        ['id' => 22, 'name' => 'Kesempatan',          'type' => 'chance'],
        ['id' => 23, 'name' => 'Yogyakarta',          'type' => 'property', 'group' => 'red', 'price' => 220, 'rent' => [18, 90, 250, 700, 875, 1050]],
        ['id' => 24, 'name' => 'Solo',                'type' => 'property', 'group' => 'red', 'price' => 240, 'rent' => [20, 100, 300, 750, 925, 1100]],
        ['id' => 25, 'name' => 'Stasiun Tugu',        'type' => 'station', 'price' => 200],
        ['id' => 26, 'name' => 'Medan',               'type' => 'property', 'group' => 'yellow', 'price' => 260, 'rent' => [22, 110, 330, 800, 975, 1150]],
        ['id' => 27, 'name' => 'Makassar',            'type' => 'property', 'group' => 'yellow', 'price' => 260, 'rent' => [22, 110, 330, 800, 975, 1150]],
        ['id' => 28, 'name' => 'Air Tirta',           'type' => 'utility', 'price' => 150],
        ['id' => 29, 'name' => 'Balikpapan',          'type' => 'property', 'group' => 'yellow', 'price' => 280, 'rent' => [24, 120, 360, 850, 1025, 1200]],
        ['id' => 30, 'name' => 'Masuk Penjara',       'type' => 'go_to_jail'],
        ['id' => 31, 'name' => 'Denpasar',            'type' => 'property', 'group' => 'green', 'price' => 300, 'rent' => [26, 130, 390, 900, 1100, 1275]],
        ['id' => 32, 'name' => 'Manado',              'type' => 'property', 'group' => 'green', 'price' => 300, 'rent' => [26, 130, 390, 900, 1100, 1275]],
        ['id' => 33, 'name' => 'Dana Umum',           'type' => 'chest'],
        ['id' => 34, 'name' => 'Batam',               'type' => 'property', 'group' => 'green', 'price' => 320, 'rent' => [28, 150, 450, 1000, 1200, 1400]],
        ['id' => 35, 'name' => 'Stasiun Gubeng',      'type' => 'station', 'price' => 200],
        ['id' => 36, 'name' => 'Kesempatan',          'type' => 'chance'],
        ['id' => 37, 'name' => 'Jakarta',             'type' => 'property', 'group' => 'blue', 'price' => 350, 'rent' => [35, 175, 500, 1100, 1300, 1500]],
        ['id' => 38, 'name' => 'Pajak Barang Mewah',  'type' => 'tax', 'amount' => 100],
        ['id' => 39, 'name' => 'Nusantara',           'type' => 'property', 'group' => 'blue', 'price' => 400, 'rent' => [50, 200, 600, 1400, 1700, 2000]],
    ],
];