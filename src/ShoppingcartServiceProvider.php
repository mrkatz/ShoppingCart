<?php

namespace Mrkatz\Shoppingcart;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\ServiceProvider;
use Mrkatz\Shoppingcart\Cart;
use Mrkatz\Shoppingcart\Listener\ShoppingCartEventListener;

class ShoppingcartServiceProvider extends ServiceProvider
{
    protected $config = __DIR__ . '/../config/cart.php';
    public function register()
    {
        $this->app->singleton(Cart::class, function () {
            return new Cart();
        });
        $this->app->alias(Cart::class, 'cart');


        $this->mergeConfigFrom($this->config, 'cart');

        $this->registerMigrations();
    }

    public function boot()
    {
        $this->registerEventListeners();
        $this->publishes([$this->config => config_path('cart.php')], 'config');
        $this->publishes([
            '/database/migrations' => database_path('migrations/'),
        ], 'migrations');
    }

    protected function registerMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function registerEventListeners()
    {
        $events = $this->app->events;

        $events->listen(Login::class, ShoppingCartEventListener::class . '@onLogin');
        $events->listen(Logout::class, ShoppingCartEventListener::class . '@onLogout');
    }
}
