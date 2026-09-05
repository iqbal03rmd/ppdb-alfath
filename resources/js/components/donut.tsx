export interface IrisanDonut {
    label: string;
    jumlah: number;
    warna: string;
}

/**
 * Donat sederhana, digambar langsung dengan SVG - tidak ada library chart di
 * proyek ini dan tidak perlu ditambah cuma untuk dua diagram.
 *
 * Caranya: satu lingkaran per irisan, kelilingnya sengaja dibuat pas 100 satuan
 * (r = 15.915) supaya panjang tiap irisan bisa langsung diisi PERSENnya tanpa
 * hitungan sudut. `strokeDashoffset` menggeser irisan berikutnya ke ujung yang
 * sebelumnya.
 *
 * Legenda WAJIB ikut, dan tiap barisnya menyebut angka - identitas irisan tidak
 * boleh bergantung pada warna saja, dan sebagian orang tidak bisa membedakan
 * merah dari hijau.
 */
export default function Donut({ irisan, kalimatKosong }: { irisan: IrisanDonut[]; kalimatKosong: string }) {
    const total = irisan.reduce((jumlah, i) => jumlah + i.jumlah, 0);
    const terisi = irisan.filter((i) => i.jumlah > 0);

    if (total === 0) {
        return <p className="text-sm text-gray-500">{kalimatKosong}</p>;
    }

    // Sela antar irisan supaya batasnya kelihatan tanpa garis pemisah. Tidak
    // dipakai kalau cuma ada satu irisan - lingkaran penuh tidak butuh sela,
    // dan memberinya sela malah bikin ada potongan yang tidak ada artinya.
    const sela = terisi.length > 1 ? 0.6 : 0;

    let mulai = 0;

    return (
        <div className="flex flex-wrap items-center gap-6">
            <svg viewBox="0 0 42 42" className="h-36 w-36 shrink-0 -rotate-90" role="img" aria-label={`Total ${total}`}>
                {terisi.map((i) => {
                    const persen = (i.jumlah / total) * 100;
                    const offset = -mulai;
                    mulai += persen;

                    return (
                        <circle
                            key={i.label}
                            cx="21"
                            cy="21"
                            r="15.915"
                            fill="none"
                            stroke={i.warna}
                            strokeWidth="5"
                            strokeDasharray={`${Math.max(persen - sela, 0.4)} ${100 - Math.max(persen - sela, 0.4)}`}
                            strokeDashoffset={offset}
                        />
                    );
                })}
                {/* Angka total di tengah - dikembalikan tegak karena svg-nya diputar. */}
                <text x="21" y="21" textAnchor="middle" dominantBaseline="central" transform="rotate(90 21 21)" className="fill-[#0A3981]">
                    <tspan fontSize="7" fontWeight="700">
                        {total}
                    </tspan>
                </text>
            </svg>

            <ul className="min-w-0 flex-1 space-y-1.5">
                {irisan.map((i) => (
                    <li key={i.label} className="flex items-center gap-2.5 text-sm">
                        <span aria-hidden className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: i.warna }} />
                        <span className="min-w-0 flex-1 truncate text-gray-700">{i.label}</span>
                        <span className="shrink-0 font-semibold text-gray-900">{i.jumlah}</span>
                        <span className="w-10 shrink-0 text-right text-xs text-gray-500">{Math.round((i.jumlah / total) * 100)}%</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
