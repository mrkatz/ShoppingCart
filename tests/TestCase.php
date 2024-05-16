<?php

namespace Mrkatz\Tests\Shoppingcart;

use Mrkatz\Shoppingcart\Cart;
use Mrkatz\Shoppingcart\CartItem;
use Mrkatz\Shoppingcart\ShoppingcartServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PHPUnit\Framework\Assert;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [ShoppingcartServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'cart' => Cart::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cart.database.connection', 'testing');

        $app['config']->set('session.driver', 'array');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator) {
            $migrator->path(realpath(__DIR__ . '/../database/migrations'));
        });
    }

    public function getCart()
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Cart($session, $events);
    }

    public function setConfigFormat($decimals, $decimalPoint, $thousandSeperator)
    {
        $this->app['config']->set('cart.format.decimals', $decimals);
        $this->app['config']->set('cart.format.decimal_point', $decimalPoint);
        $this->app['config']->set('cart.format.thousand_seperator', $thousandSeperator);
    }

    public function rowId(CartItem $cartItem)
    {
        return $cartItem->rowId;
    }

    public function assertItemsInCart($items, Cart $cart)
    {
        $actual = $cart->count();

        Assert::assertEquals($items, $cart->count(), "Expected the cart to contain {$items} items, but got {$actual}.");
    }

    public function assertRowsInCart($rows, Cart $cart)
    {
        $actual = $cart->content()->count();

        Assert::assertCount($rows, $cart->content(), "Expected the cart to contain {$rows} rows, but got {$actual}.");
    }
}
