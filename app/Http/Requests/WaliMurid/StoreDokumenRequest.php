<?php

namespace App\Http\Requests\WaliMurid;

use App\Models\BerkasPersyaratan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Daftarnya diambil dari master Berkas Persyaratan, bukan ditulis
            // di sini. Jenis yang sudah dinonaktifkan ikut ditolak - unggahan
            // buat berkas yang tidak diminta lagi tidak punya tempat di
            // checklist mana pun.
            'jenis_dokumen' => ['required', Rule::in(array_keys(BerkasPersyaratan::peta()))],
            'berkas' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'berkas.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'berkas.max' => 'Ukuran berkas maksimal 2 MB.',
        ];
    }
}