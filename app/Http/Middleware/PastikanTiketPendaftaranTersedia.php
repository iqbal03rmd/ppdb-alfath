<?php

namespace App\Http\Middleware;

use App\Models\GelombangPpdb;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanTiketPendaftaranTersedia
{
    public function handle(Request $request, Closure $next): Response
    {
        $gelombang = GelombangPpdb::menerimaPendaftar()->latest()->first();

        if (! $gelombang || ! $request->user()?->tiketPendaftaranTersedia($gelombang->id)->exists()) {
            return redirect()->route('wali-murid.biaya-pendaftaran.show')->with(
                'error',
                'Selesaikan biaya pendaftaran anak terlebih dahulu sebelum mengisi formulir.'
            );
        }

        return $next($request);
    }
}
