<?php

namespace App\Http\Requests\WaliMurid;

use Illuminate\Foundation\Http\FormRequest;

class StorePembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nominal_transfer' => ['required', 'numeric', 'min:1'],
            'tanggal_transfer' => ['required', 'date', 'before_or_equal:today'],
            'bukti_transfer' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'bukti_transfer.mimes' => 'Bukti transfer harus berformat PDF, JPG, atau PNG.',
            'bukti_transfer.max' => 'Ukuran berkas maksimal 2 MB.',
            'tanggal_transfer.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
        ];
    }
}
