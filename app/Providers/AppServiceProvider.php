<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Mikrotik\MikrotikClient;
use App\Services\Mikrotik\RouterOSClient;
use App\Services\Mikrotik\NullMikrotikClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $driver = config('mikrotik.driver', env('MIKROTIK_DRIVER', 'routeros'));

        $this->app->bind(MikrotikClient::class, function () use ($driver) {
            return $driver === 'routeros'
                ? new RouterOSClient()   // <- driver beneran
                : new NullMikrotikClient();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
