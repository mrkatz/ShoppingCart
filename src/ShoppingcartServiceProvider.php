<?php

namespace Mrkatz\Shoppingcart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;
use Mrkatz\Shoppingcart\Listener\ShoppingCartEventListener;
use Illuminate\Support\Facades\Event;

class ShoppingcartServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->registerEventListeners();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('cart', 'Mrkatz\Shoppingcart\Cart');

        $config = __DIR__ . '/../config/cart.php';
        $this->mergeConfigFrom($config, 'cart');

        $this->publishes([__DIR__ . '/../config/cart.php' => config_path('cart.php')], 'config');

        if (!class_exists('CreateShoppingcartTable')) {

            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/../database/migrations/0000_00_00_000000_create_shoppingcart_table.php' => database_path('migrations/' . $timestamp . '_create_shoppingcart_table.php'),
            ], 'migrations');
        }
        if (!class_exists('CreateCouponsTable')) {

            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/../database/migrations/0000_00_00_000000_create_coupons_table.php' => database_path('migrations/' . $timestamp . '_create_coupons_table.php'),
            ], 'migrations');
        }
        if (!class_exists('CreateCartFeesTable')) {

            $timestamp = date('Y_m_d_His', time());

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
