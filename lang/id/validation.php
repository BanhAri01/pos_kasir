<?php

/*
 * Pesan validasi cadangan dalam bahasa sehari-hari.
 * Form penting sebaiknya tetap menulis pesan khususnya sendiri di Form Request
 * (yang menjelaskan apa yang salah DAN apa yang harus dilakukan).
 */
return [
    'accepted' => ':Attribute perlu disetujui.',
    'array' => ':Attribute tidak valid.',
    'boolean' => ':Attribute harus dipilih Ya atau Tidak.',
    'confirmed' => ':Attribute tidak sama. Coba ketik ulang.',
    'date' => ':Attribute bukan tanggal yang benar.',
    'digits' => ':Attribute harus :digits angka.',
    'digits_between' => ':Attribute harus :min sampai :max angka.',
    'email' => ':Attribute bukan alamat email yang benar.',
    'exists' => ':Attribute yang dipilih tidak ditemukan.',
    'in' => ':Attribute yang dipilih tidak tersedia.',
    'integer' => ':Attribute harus berupa angka bulat.',
    'max' => [
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string' => ':Attribute terlalu panjang. Maksimal :max huruf.',
        'array' => 'Paling banyak :max pilihan.',
    ],
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min huruf.',
        'array' => 'Pilih minimal :min.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'prohibited' => ':Attribute tidak boleh diisi.',
    'regex' => 'Format :attribute tidak benar.',
    'required' => ':Attribute belum diisi.',
    'required_if' => ':Attribute belum diisi.',
    'string' => ':Attribute tidak valid.',
    'unique' => ':Attribute ini sudah dipakai.',

    'attributes' => [
        'name' => 'nama',
        'phone' => 'no HP',
        'password' => 'kata sandi',
        'pin' => 'PIN',
        'address' => 'alamat',
        'role' => 'jabatan',
        'job_title' => 'sebutan pekerjaan',
        'outlet_ids' => 'outlet',
        'outlet_ids.*' => 'outlet',
        'business_name' => 'nama usaha',
        'owner_name' => 'nama Anda',
        'business_type' => 'jenis usaha',
        'enabled' => 'pilihan',
    ],
];
