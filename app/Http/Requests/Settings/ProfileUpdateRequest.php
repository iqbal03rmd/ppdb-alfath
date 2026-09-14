<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Rules\NomorWhatsApp;
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
            'telepon' => [
                'nullable',
                'string',
                'max:20',
                new NomorWhatsApp,
            ],
            'notifikasi_whatsapp_aktif' => ['sometimes', 'boolean'],
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

    public function after(): array
    {
        return [
            function ($validator): void {
                $aktif = $this->has('notifikasi_whatsapp_aktif')
                    ? $this->boolean('notifikasi_whatsapp_aktif')
                    : (bool) $this->user()->notifikasi_whatsapp_aktif;

                $telepon = $this->has('telepon') ? $this->input('telepon') : $this->user()->telepon;

                if ($aktif && trim((string) $telepon) === '') {
                    $validator->errors()->add(
                        'telepon',
                        'Isi nomor WhatsApp atau nonaktifkan notifikasi WhatsApp terlebih dahulu.'
                    );
                }
            },
        ];
    }
}
