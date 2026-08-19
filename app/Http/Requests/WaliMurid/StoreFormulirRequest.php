<?php

namespace App\Http\Requests\WaliMurid;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormulirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kategori_siswa_id' => ['required', 'exists:kategori_siswa,id'],
            'nama_pendaftar' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'digits:16'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:laki-laki,perempuan'],
            'agama' => ['nullable', 'string', 'max:50'],
            'alamat' => ['required', 'string'],

            'nama_saudara' => ['nullable', 'string', 'max:255'],
            'nama_orang_tua_guru' => ['nullable', 'string', 'max:255'],

            // Data wali_murid, minimal 1, bisa lebih dari 1 (repeatable)
            'wali_murid' => ['required', 'array', 'min:1'],
            'wali_murid.*.nama' => ['required', 'string', 'max:255'],
            'wali_murid.*.nik' => ['required', 'digits:16'],
            'wali_murid.*.hubungan' => ['required', 'string', 'max:50'],
            'wali_murid.*.telepon' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'wali_murid.required' => 'Minimal satu data wali murid harus diisi.',
            'wali_murid.*.nik.digits' => 'NIK wali murid harus 16 digit angka.',
            'nik.digits' => 'NIK calon peserta didik harus 16 digit angka.',
        ];
    }
}