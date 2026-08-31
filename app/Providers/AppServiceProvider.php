<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The default paginator views assume Tailwind; this app ships its own CSS.
        Paginator::defaultView('pagination::admin');
        Paginator::defaultSimpleView('pagination::admin');

        // Behind nginx the app is reached over http on a mapped port; force the
        // configured scheme so generated URLs stay consistent.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Cache buster for the hand-written CSS/JS. Appending the newest asset
        // mtime means an edited stylesheet is picked up without a hard refresh.
        View::share('assetVersion', $this->assetVersion());
    }

    private function assetVersion(): int
    {
        $stamps = [0];

        foreach (['css/admin.css', 'js/admin.js', 'favicon.svg'] as $asset) {
            $path = public_path($asset);

            if (is_file($path)) {
                $stamps[] = (int) filemtime($path);
            }
        }

        return max($stamps);
    }
}
