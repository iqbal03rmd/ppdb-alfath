/**
 * Isian formulir bergaya aplikasi ini. Dipakai bersama, jangan disalin lagi.
 *
 * Sebelumnya tiap halaman formulir mendefinisikan Label/Input/FieldError-nya
 * sendiri di kaki berkas. Dua salinan masih bisa dibiarkan; begitu muncul yang
 * ketiga (halaman Pengaturan), salinannya mulai berbeda-beda sendiri dan
 * warnanya pelan-pelan tidak lagi sama antar halaman.
 *
 * `pendaftaran-create.tsx` SENGAJA belum ikut dipindahkan ke sini: berkas itu
 * punya varian sendiri (Select ber-optgroup, Section) dan panjangnya 771 baris,
 * jadi memindahkannya pekerjaan tersendiri - bukan tempelan di pekerjaan lain.
 */

export function Kartu({ judul, children, className = '' }: { judul: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={`rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] ${className}`}>
            <h2 className="mb-5 text-[15px] font-semibold text-gray-900">{judul}</h2>
            {children}
        </div>
    );
}

export function Label({ children, required, htmlFor }: { children: React.ReactNode; required?: boolean; htmlFor: string }) {
    return (
        <label htmlFor={htmlFor} className="mb-1.5 block text-[13px] font-medium text-gray-600">
            {children}
            {required && <span className="ml-0.5 text-red-500">*</span>}
        </label>
    );
}

/**
 * `id` sengaja WAJIB, bukan opsional: itu yang menyambungkan kolom ini ke
 * labelnya. Kalau boleh dikosongkan, kolom yang ditambahkan orang berikutnya
 * akan lupa lagi - sekarang TypeScript yang mengingatkan, bukan manusia.
 */
export function Input({
    id,
    value,
    onChange,
    placeholder,
    type = 'text',
    autoComplete,
    disabled,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    type?: 'text' | 'email' | 'password';
    autoComplete?: string;
    /** Buat layar yang merangkap baca-saja. Server tetap yang menjaga. */
    disabled?: boolean;
}) {
    return (
        <input
            id={id}
            type={type}
            autoComplete={autoComplete}
            disabled={disabled}
            className="w-full rounded-lg border border-gray-200 bg-[#F5F9FD] px-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:border-[#1F509A] focus:bg-white focus:ring-2 focus:ring-[#1F509A]/15 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-600"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
        />
    );
}

export function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-600">{message}</p>;
}
