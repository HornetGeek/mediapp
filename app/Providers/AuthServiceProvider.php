<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\Company;
use App\Models\Doctors;
use App\Models\Representative;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;


class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::provider('doctors', function ($app, array $config) {
            return new EloquentUserProvider($app['hash'], Doctors::class);
        });
    
        Auth::provider('companies', function ($app, array $config) {
            return new EloquentUserProvider($app['hash'], Company::class);
        });
    
        Auth::provider('representatives', function ($app, array $config) {
            return new EloquentUserProvider($app['hash'], Representative::class);
        });
    }
}
