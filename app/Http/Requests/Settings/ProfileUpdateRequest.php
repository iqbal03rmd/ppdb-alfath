<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            // Batas 20 disamakan dengan SuperAdmin\PenggunaController: kolom yang
            // sama, jadi tidak boleh ada nomor yang lolos lewat satu halaman tapi
            // ditolak di halaman lain.
            'telepon' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi - itu yang dipakai untuk masuk.',
            'email.lowercase' => 'Tulis email dengan huruf kecil semua.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
        ];
    }
}
