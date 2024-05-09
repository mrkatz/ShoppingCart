<?php

namespace Mrkatz\Shoppingcart\Listener;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Session\SessionManager;
use Mrkatz\Shoppingcart\Facades\Cart;

class ShoppingCartEventListener
{
    protected $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    public function onLogin(Login $event)
    {
        if (config('cart.database.save_on_logout')) {

            foreach (config('cart.database.save_instances') as $cart) {
                if (Cart::instance($cart)->count() === 0) {
                    Cart::instance($cart)->restore($event->user->id);
                }
            }
        }
    }

    public function onLogout(Logout $event)
    {
        if (config('cart.destroy_on_logout')) {
            $this->session->forget('cart');
        } elseif (config('cart.database.save_on_logout')) {

            foreach (config('cart.database.save_instances') as $cart) {
                if (Cart::instance($cart)->count() > 0) {
                    Cart::instance($cart)->store($event->user->id);
                }
            }
        }
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
        ];
    }
}
