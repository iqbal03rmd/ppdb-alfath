<?php

namespace App\Http\Middleware;

use App\Models\PengaturanSistem;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $pengaturan = PengaturanSistem::saatIni();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'sistem' => [
                'nama_sekolah' => $pengaturan->nama_sekolah,
                'tagline' => $pengaturan->tagline,
            ],
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'home_url' => $request->user() ? route($request->user()->homeRouteName()) : null,
            ],
            'flash' => [
                'error' => $request->session()->get('error'),
                'success' => $request->session()->get('success'),
            ],
        ]);
    }
}
