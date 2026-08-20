<?php

namespace App\Http\Requests\WaliMurid;

use Illuminate\Foundation\Http\FormRequest;

class StoreDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_dokumen' => [
                'required',
                'in:kartu_keluarga,akta,ktp_orangtua,pas_foto,surat_kematian_ayah,surat_keterangan_tidak_mampu',
            ],
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