<?php

namespace App\Http\Requests\WaliMurid;

use App\Models\PendaftaranPpdb;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'alamat' => ['required', 'string'],

            // Bagian alamat - seluruhnya teks bebas, termasuk kecamatan.
            // Tidak ada yang diagregasi jadi laporan, jadi tidak ada yang perlu
            // dikunci ke daftar pilihan. Lihat catatan di migration.
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:255'],
            'kota_kabupaten' => ['required', 'string', 'max:255'],
            'provinsi' => ['required', 'string', 'max:255'],

            // --- Isian untuk laporan Kepala Sekolah -------------------------
            'tanpa_paud' => ['required', 'boolean'],
            'asal_paud_id' => ['nullable', 'exists:asal_paud,id'],
            'asal_paud_lainnya' => ['nullable', 'string', 'max:255'],

            // Satu-satunya isian yang boleh dilewati. Kalau diwajibkan, wali
            // yang tidak ingat akan asal pilih supaya bisa lanjut - dan jawaban
            // asal lebih merusak laporan daripada tidak ada jawaban.
            'tahu_dari' => ['nullable', Rule::in(array_keys(PendaftaranPpdb::SUMBER_INFORMASI))],
            'tahu_dari_lainnya' => ['nullable', 'string', 'max:255', 'required_if:tahu_dari,lainnya'],

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

    /**
     * Asal PAUD punya TIGA jawaban sah - pilih dari daftar, ketik sendiri, atau
     * menyatakan tidak lewat PAUD - dan salah satunya wajib ada. Aturan seperti
     * itu tidak bisa ditulis sebagai rule per kolom tanpa jadi berbelit, jadi
     * ditulis sekali di sini sebagai satu kalimat yang bisa dibaca.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->boolean('tanpa_paud')) {
                    return;
                }

                if ($this->filled('asal_paud_id') || $this->filled('asal_paud_lainnya')) {
                    return;
                }

                $validator->errors()->add(
                    'asal_paud_id',
                    'Pilih sekolah asal anak, ketik sendiri kalau belum ada di daftar, atau centang "Belum/tidak ikut PAUD".'
                );
            },
        ];
    }

    public function messages(): array
    {
        return [
            'wali_murid.required' => 'Minimal satu data wali murid harus diisi.',
            'wali_murid.*.nik.digits' => 'NIK wali murid harus 16 digit angka.',
            'nik.digits' => 'NIK calon peserta didik harus 16 digit angka.',
            'tahu_dari_lainnya.required_if' => 'Sebutkan dari mana Anda tahu PPDB ini.',
        ];
    }
}