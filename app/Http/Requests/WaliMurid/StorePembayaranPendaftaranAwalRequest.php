<?php

namespace App\Http\Requests\WaliMurid;

use Illuminate\Foundation\Http\FormRequest;

class StorePembayaranPendaftaranAwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nominal_transfer' => ['required', 'integer', 'min:1'],
            'tanggal_transfer' => ['required', 'date', 'before_or_equal:today'],
            'bukti_transfer' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'nominal_transfer.required' => 'Nominal transfer wajib diisi.',
            'nominal_transfer.min' => 'Nominal transfer harus lebih dari nol.',
            'tanggal_transfer.required' => 'Tanggal transfer wajib diisi.',
            'tanggal_transfer.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
            'bukti_transfer.required' => 'Bukti transfer wajib diunggah.',
            'bukti_transfer.mimes' => 'Bukti transfer harus berformat PDF, JPG, atau PNG.',
            'bukti_transfer.max' => 'Ukuran berkas maksimal 2 MB.',
        ];
    }
}
