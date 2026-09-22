import { Check, FileText, ReceiptText, UploadCloud, UserPlus, WalletCards, type LucideIcon } from 'lucide-react';

const LANGKAH = ['Registrasi Akun', 'Biaya Pendaftaran', 'Formulir', 'Unggah Berkas', 'Pembayaran Sekolah'] as const;

export type LangkahAlur = (typeof LANGKAH)[number];

const IKON_LANGKAH: Record<LangkahAlur, LucideIcon> = {
    'Registrasi Akun': UserPlus,
    'Biaya Pendaftaran': ReceiptText,
    Formulir: FileText,
    'Unggah Berkas': UploadCloud,
    'Pembayaran Sekolah': WalletCards,
};

/**
 * Penanda kemajuan alur pendaftaran PPDB - SATU-SATUNYA definisi urutan langkah.
 * Dipakai sejak Biaya Pendaftaran sampai Pembayaran Sekolah. Langkah sebelum yang aktif
 * otomatis ditandai selesai, jadi pemanggil cukup menyebut posisinya sekarang.
 *
 * Pada layar kecil label teks disembunyikan dan hanya lingkarannya yang tampil,
 * supaya lima langkah tetap muat tanpa membuat halaman menggeser ke samping.
 */
export default function AlurStepper({ aktif }: { aktif: LangkahAlur }) {
    const indeksAktif = LANGKAH.indexOf(aktif);

    return (
        <div
            className="mb-8 flex items-center justify-center lg:justify-start"
            aria-label={`Langkah ${indeksAktif + 1} dari ${LANGKAH.length}: ${aktif}`}
        >
            {LANGKAH.map((label, i) => {
                const state = i < indeksAktif ? 'done' : i === indeksAktif ? 'active' : 'pending';
                const Ikon = IKON_LANGKAH[label];

                const lingkaran =
                    state === 'done'
                        ? 'border-green-500 bg-green-500 text-white'
                        : state === 'active'
                          ? 'border-[#0A3981] bg-[#0A3981] text-white shadow-[0_0_0_4px_rgba(10,57,129,0.12)]'
                          : 'border-gray-200 bg-white text-gray-400';

                const teks = state === 'active' ? 'font-semibold text-[#0A3981]' : state === 'done' ? 'text-gray-500' : 'text-gray-400';

                return (
                    <div key={label} className="flex items-center">
                        {i > 0 && <div className="mx-1 h-0.5 w-4 bg-[#D4EBF8] sm:mx-2 sm:w-10 lg:mx-3 lg:w-14" />}
                        <div className="flex items-center">
                            <div
                                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 transition-all ${lingkaran}`}
                                aria-label={`${label}: ${state === 'done' ? 'selesai' : state === 'active' ? 'sedang berlangsung' : 'belum dimulai'}`}
                            >
                                {state === 'done' ? (
                                    <Check className="h-4.5 w-4.5" strokeWidth={3} aria-hidden="true" />
                                ) : (
                                    <Ikon className="h-4 w-4" aria-hidden="true" />
                                )}
                            </div>
                            <span className={`ml-2 hidden text-[13px] lg:inline ${teks}`}>{label}</span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
