<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | Berkas ini ada CUMA untuk membetulkan satu hal: letak folder halaman.
    |
    | Bawaan Inertia mencari halaman di `resources/js/Pages` (P besar), padahal
    | proyek ini memakai `resources/js/pages` (p kecil) - lihat resources/js/app.tsx.
    |
    | Di Windows perbedaan itu tidak terasa karena nama berkasnya tidak
    | membedakan huruf besar-kecil, jadi `artisan test` lolos di laptop. Di
    | Linux (CI/GitHub Actions) namanya dibedakan, dan setiap test yang memakai
    | ->component('staf-ppdb/dashboard') gagal dengan pesan
    | "Inertia page component file does not exist".
    |
    | Yang salah cuma jalur pencarian milik test; aplikasinya sendiri tidak
    | pernah bermasalah karena Vite memakai glob './pages/**' sendiri.
    |
    | Seluruh isi blok 'testing' harus ditulis ulang di sini, tidak cukup
    | page_paths saja: config aplikasi menimpa milik paket per kunci teratas,
    | bukan digabung sampai ke dalam.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

];
