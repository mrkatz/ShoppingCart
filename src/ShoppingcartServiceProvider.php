<?php

namespace Mrkatz\Shoppingcart;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\ServiceProvider;
use Mrkatz\Shoppingcart\Cart;
use Mrkatz\Shoppingcart\Listener\ShoppingCartEventListener;

class ShoppingcartServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Cart::class, function ($app) {
            return new Cart($app['session'], $app['events']);
        });
        $this->app->alias(Cart::class, 'cart');

        $config = __DIR__ . '/../config/cart.php';
        $this->mergeConfigFrom($config, 'cart');
        $this->publishes([__DIR__ . '/../config/cart.php' => config_path('cart.php')], 'config');

        $this->registerMigrations();
    }

    public function boot()
    {
        $this->registerEventListeners();
    }

    protected function registerMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->publishes([
            '/database/migrations' => database_path('migrations/'),
        ], 'migrations');
    }

    protected function registerEventListeners()
    {
        $events = $this->app->events;

        $events->listen(Login::class, ShoppingCartEventListener::class . '@onLogin');
        $events->listen(Logout::class, ShoppingCartEventListener::class . '@onLogout');
    }
}
