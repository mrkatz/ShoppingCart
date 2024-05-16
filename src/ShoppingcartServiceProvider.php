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

        $this->migrations();
    }

    public function boot()
    {
        $this->registerEventListeners();
    }

    protected function migrations()
    {
        $timestamp = date('Y_m_d_His', time());
        if (!class_exists('CreateShoppingcartTable')) {
            $this->publishes([
                __DIR__ . '/../database/migrations/0000_00_00_000000_create_shoppingcart_table.php' => database_path('migrations/' . $timestamp . '_create_shoppingcart_table.php'),
            ], 'migrations');
        }
        if (!class_exists('CreateCouponsTable')) {
            $this->publishes([
                __DIR__ . '/../database/migrations/0000_00_00_000000_create_coupons_table.php' => database_path('migrations/' . $timestamp . '_create_coupons_table.php'),
            ], 'migrations');
        }
        if (!class_exists('CreateCartFeesTable')) {
            $this->publishes([
                __DIR__ . '/../database/migrations/0000_00_00_000000_create_cartfees_table.php' => database_path('migrations/' . $timestamp . '_create_cartfees_table.php'),
            ], 'migrations');
        }
    }

    protected function registerEventListeners()
    {
        $events = $this->app->events;

        $events->listen(Login::class, ShoppingCartEventListener::class . '@onLogin');
        $events->listen(Logout::class, ShoppingCartEventListener::class . '@onLogout');
    }
}
