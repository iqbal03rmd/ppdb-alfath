import { Head } from '@inertiajs/react';

interface PendaftaranProps {
    pendaftaran: {
        id: number;
        nomor_pendaftaran: string;
        nama_pendaftar: string;
    };
}

export default function UnggahBerkas({ pendaftaran }: PendaftaranProps) {
    return (
        <>
            <Head title="Unggah Berkas" />
            <div style={{ padding: 40 }}>
                <h1>Unggah Berkas</h1>
                <p>Halaman ini placeholder — isi aslinya menyusul.</p>
                <p>
                    Nomor pendaftaran: <b>{pendaftaran.nomor_pendaftaran}</b>
                    <br />
                    Nama: <b>{pendaftaran.nama_pendaftar}</b>
                </p>
            </div>
        </>
    );
}