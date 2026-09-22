<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Satu aturan kecil untuk memvalidasi sekaligus menormalkan nomor tujuan.
 */
class NomorWhatsApp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalisasi($value) === null) {
            $fail('Nomor WhatsApp harus berupa nomor seluler Indonesia yang valid.');
        }
    }

    public static function normalisasi(?string $nomor): ?string
    {
        if ($nomor === null || trim($nomor) === '') {
            return null;
        }

        $nomor = trim($nomor);

        // Spasi, tanda hubung, dan kurung masih lazim dipakai saat menulis
        // nomor. Huruf atau simbol lain tidak boleh diam-diam dibuang karena
        // ketikan seperti "WA 0812..." seharusnya gagal validasi, bukan lolos.
        if (preg_match('/^\+?[0-9\s().-]+$/', $nomor) !== 1) {
            return null;
        }

        $angka = preg_replace('/\D+/', '', $nomor);

        if ($angka === null || $angka === '') {
            return null;
        }

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        } elseif (str_starts_with($angka, '8')) {
            $angka = '62'.$angka;
        }

        return preg_match('/^628\d{7,12}$/', $angka) === 1 ? $angka : null;
    }
}
