<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            $this->app->register(\Laravel\Horizon\HorizonServiceProvider::class);
        }

        // Check if Redis PHP extension is available
        $redisExtensionAvailable = extension_loaded('redis') || class_exists('Redis');
        
        // Check if Predis is available (client PHP pur)
        $predisAvailable = class_exists('Predis\Client');
        
        // Use Predis if PHP Redis extension is not available but Predis is
        if (!$redisExtensionAvailable && $predisAvailable) {
            config([
                'database.redis.client' => 'predis',
            ]);
        }
        
        // Only force database fallback if neither Redis extension nor Predis is available
        // If user explicitly configured Redis in .env, allow it (they can use Predis)
        if (!$redisExtensionAvailable && !$predisAvailable) {
            // Force queue to use database if Redis is not available
            if (config('queue.default') === 'redis') {
                config(['queue.default' => 'database']);
            }

            // Force cache to use database if Redis is not available
            if (config('cache.default') === 'redis') {
                config(['cache.default' => 'database']);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            \Laravel\Horizon\Horizon::auth(function ($request) {
                $user = $request->user();

                return $user && method_exists($user, 'is_admin') ? (bool) $user->is_admin : false;
            });
        }
    }
}
