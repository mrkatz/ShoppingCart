<?php

namespace Mrkatz\Tests\Shoppingcart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mrkatz\Shoppingcart\Cart;
use Mrkatz\Shoppingcart\CartCoupon;
use Mrkatz\Shoppingcart\CartItem;
use Mrkatz\Shoppingcart\Exceptions\InvalidRowIDException;
use Mrkatz\Shoppingcart\Exceptions\UnknownModelException;
use Mrkatz\Shoppingcart\Models\Coupon;
use Mrkatz\Shoppingcart\ShoppingcartServiceProvider;
use Mrkatz\Tests\Shoppingcart\Fixtures\BuyableProduct;
use Mrkatz\Tests\Shoppingcart\Fixtures\ProductModel;
use Mrkatz\Tests\Shoppingcart\Fixtures\TestUser;

class CartTest extends TestCase
{
    use CartAssertions;

    /**
     * Set the package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ShoppingcartServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
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

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator) {
            $migrator->path(realpath(__DIR__ . '/../database/migrations'));
        });
    }

    public function test_it_has_a_default_instance()
    {
        $cart = $this->getCart();

        $this->assertEquals(Cart::DEFAULT_INSTANCE, $cart->currentInstance());
    }

    public function test_it_can_have_multiple_instances()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item'));

        $cart->instance('wishlist')->add(new BuyableProduct(2, 'Second item'));

        $this->assertItemsInCart(1, $cart->instance(Cart::DEFAULT_INSTANCE));
        $this->assertItemsInCart(1, $cart->instance('wishlist'));
    }

    public function test_it_can_add_an_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_will_return_the_cartitem_of_the_added_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('027c91341fd5cf4d2579b49c4b6a90da', $cartItem->rowId);

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_multiple_buyable_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_will_return_an_array_of_cartitems_when_you_add_multiple_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItems = $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertTrue(is_array($cartItems));
        $this->assertCount(2, $cartItems);
        $this->assertContainsOnlyInstancesOf(CartItem::class, $cartItems);

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_an_item_from_attributes()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_an_item_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(['id' => 1, 'name' => 'Test item', 'qty' => 1, 'price' => 10.00]);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_multiple_array_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([
            ['id' => 1, 'name' => 'Test item 1', 'qty' => 1, 'price' => 10.00],
            ['id' => 2, 'name' => 'Test item 2', 'qty' => 1, 'price' => 10.00]
        ]);

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_an_item_with_options()
    {
        Event::fake();

        $cart = $this->getCart();

        $options = ['size' => 'XL', 'color' => 'red'];

        $cart->add(new BuyableProduct, 1, $options);

        $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('XL', $cartItem->options->size);
        $this->assertEquals('red', $cartItem->options->color);

        Event::assertDispatched('cart.added');
    }

    /**
     * 
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid identifier.
     */
    public function test_it_will_validate_the_identifier()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid identifier.');

        $cart = $this->getCart();

        $cart->add(null, 'Some title', 1, 10.00);
    }

    public function test_it_will_validate_the_name()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid name.');
        $cart = $this->getCart();

        $cart->add(1, null, 1, 10.00);
    }

    public function test_it_will_validate_the_quantity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid quantity.');
        $cart = $this->getCart();

        $cart->add(1, 'Some title', 'invalid', 10.00);
    }

    public function test_it_will_validate_the_price()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid price.');
        $cart = $this->getCart();

        $cart->add(1, 'Some title', 1, 'invalid');
    }

    public function test_it_will_update_the_cart_if_the_item_already_exists_in_the_cart()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct;

        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    public function test_it_will_keep_updating_the_quantity_when_an_item_is_added_multiple_times()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct;

        $cart->add($item);
        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(3, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    public function test_it_can_update_the_quantity_of_an_existing_item_in_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 2);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        Event::assertDispatched('cart.updated');
    }

    public function test_it_can_update_an_existing_item_in_the_cart_from_a_buyable()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', new BuyableProduct(1, 'Different description'));

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    public function test_it_can_update_an_existing_item_in_the_cart_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', ['name' => 'Different description']);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    public function test_it_will_throw_an_exception_if_a_rowid_was_not_found()
    {
        $this->expectException(InvalidRowIDException::class);
        $this->expectExceptionMessage('The cart does not contain rowId none-existing-rowid.');
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('none-existing-rowid', new BuyableProduct(1, 'Different description'));
    }

    public function test_it_will_regenerate_the_rowid_if_the_options_changed()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct, 1, ['color' => 'red']);

        $cart->update('ea65e0bdcd1967c4b3149e9e780177c0', ['options' => ['color' => 'blue']]);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('7e70a1e9aaadd18c72921a07aae5d011', $cart->content()->first()->rowId);
        $this->assertEquals('blue', $cart->get('7e70a1e9aaadd18c72921a07aae5d011')->options->color);
    }

    public function test_it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_rowid()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct, 1, ['color' => 'red']);
        $cart->add(new BuyableProduct, 1, ['color' => 'blue']);

        $cart->update('7e70a1e9aaadd18c72921a07aae5d011', ['options' => ['color' => 'red']]);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    public function test_it_can_remove_an_item_from_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->remove('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_will_remove_the_item_if_its_quantity_was_set_to_zero()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 0);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_will_remove_the_item_if_its_quantity_was_set_negative()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', -1);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_can_get_an_item_from_the_cart_by_its_rowid()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(CartItem::class, $cartItem);
    }

    public function test_it_can_get_the_content_of_the_cart()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(2, $content);
    }

    public function test_it_will_return_an_empty_collection_if_the_cart_is_empty()
    {
        $cart = $this->getCart();

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(0, $content);
    }

    public function test_it_will_include_the_tax_and_subtotal_when_converted_to_an_array()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertEquals([
            '027c91341fd5cf4d2579b49c4b6a90da' => [
                'rowId' => '027c91341fd5cf4d2579b49c4b6a90da',
                'id' => 1,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'isSaved' => false,
                'options' => [],
            ],
            '370d08585360f5c568b18d1f2e4ca1df' => [
                'rowId' => '370d08585360f5c568b18d1f2e4ca1df',
                'id' => 2,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'isSaved' => false,
                'options' => [],
            ]
        ], $content->toArray());
    }

    public function test_it_can_destroy_a_cart()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $this->assertItemsInCart(1, $cart);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);
    }

    public function test_it_can_get_the_total_price_of_the_cart_content()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 10.00));
        $cart->add(new BuyableProduct(2, 'Second item', 25.00), 2);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals(60.00, $cart->subtotal());
    }

    public function test_it_can_return_a_formatted_total()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 1000.00));
        $cart->add(new BuyableProduct(2, 'Second item', 2500.00), 2);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals('6.000,00', $cart->subtotal(2, ',', '.'));
    }

    public function test_it_can_search_the_cart_for_a_specific_item()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    public function test_it_can_search_the_cart_for_multiple_items()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Some item'));
        $cart->add(new BuyableProduct(3, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
    }

    public function test_it_can_search_the_cart_for_a_specific_item_with_options()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'), 1, ['color' => 'red']);
        $cart->add(new BuyableProduct(2, 'Another item'), 1, ['color' => 'blue']);

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->options->color == 'red';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    public function test_it_will_associate_the_cart_item_with_a_model_when_you_add_a_buyable()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(BuyableProduct::class, $cartItem->associatedModel);
    }

    public function test_it_can_associate_the_cart_item_with_a_model()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(ProductModel::class, $cartItem->associatedModel);
    }

    public function test_it_will_throw_an_exception_when_a_non_existing_model_is_being_associated()
    {
        $this->expectException(UnknownModelException::class);
        $this->expectExceptionMessage('The supplied model SomeModel does not exist');

        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', 'SomeModel');
    }

    public function test_it_can_get_the_associated_model_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(ProductModel::class, $cartItem->model);
        $this->assertEquals('Some value', $cartItem->model->someValue);
    }

    public function test_it_can_calculate_the_subtotal_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 9.99), 3);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(29.97, $cartItem->subtotal);
    }

    public function test_it_can_return_a_formatted_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 500), 3);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('1.500,00', $cartItem->format('subtotal', 2, ',', '.'));
    }

    public function test_it_can_calculate_tax_based_on_the_default_tax_rate_in_the_config()
    {
        config(['cart.tax' => 21]);

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(2.10, $cartItem->tax);
    }

    public function test_it_can_calculate_tax_based_on_the_specified_tax()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(1.90, $cartItem->tax);
    }

    public function test_it_can_return_the_calculated_tax_formatted()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10000.00), 1);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('2.100,00', $cartItem->format('tax', 2, ',', '.'));
    }

    public function test_it_can_calculate_the_total_tax_for_all_cart_items()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(10.50, $cart->tax);
    }

    public function test_it_can_return_formatted_total_tax()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('1.050,00', $cart->tax(2, ',', '.'));
    }

    public function test_it_can_return_the_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(50.00, $cart->subtotal);
    }

    public function test_it_can_return_formatted_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal(2, ',', ''));
    }

    public function test_it_can_set_and_retreave_comparePrice()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', ['price' => 1000.00, 'comparePrice' => 2200.00]), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal(2, ',', ''));

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');
        $this->assertEquals('2200.00', $cartItem->comparePrice);
    }

    public function test_it_can_return_cart_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal());
        $this->assertEquals('1050,00', $cart->tax());
        $this->assertEquals('6050,00', $cart->total());

        $this->assertEquals('5000,00', $cart->subtotal);
        $this->assertEquals('1050,00', $cart->tax);
        $this->assertEquals('6050,00', $cart->total);
    }

    public function test_it_can_return_cartItem_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('2000,00', $cartItem->price(true));
        $this->assertEquals('2420,00', $cartItem->priceTax(true));
        $this->assertEquals('4000,00', $cartItem->subtotal(true));
        $this->assertEquals('4840,00', $cartItem->total(true));
        $this->assertEquals('420,00', $cartItem->tax(true));
        $this->assertEquals('840,00', $cartItem->taxTotal(true));
    }

    public function test_it_can_store_the_cart_in_a_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $serialized = serialize($cart->content());

        $this->assertDatabaseHas('shoppingcart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

        Event::assertDispatched('cart.stored');
    }

    public function test_it_can_store_multiple_carts_in_a_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);
        $identifier = '123';

        $instances = ['cart1' => null, 'cart2' => null];
        Event::fake();
        foreach ($instances as $cartName => $content) {
            $cart = $this->getCart()->instance($cartName);

            $cart->add(new BuyableProduct);

            $cart->store($identifier . $cartName);

            $instances[$cartName] = serialize($cart->content());

            Event::assertDispatched('cart.stored');
        }

        foreach ($instances as $cartName => $content) {
            $this->assertDatabaseHas('shoppingcart', ['identifier' => $identifier . $cartName, 'instance' => $cartName, 'content' => $content]);
        }
    }

    public function test_it_can_store_multiple_carts_in_a_database_onLogout_if_Set()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        config(['cart.database.save_on_logout' => true]);
        config(['cart.database.save_instances' => ['cart1', 'cart2']]);

        $user = new TestUser([
            'id' => 1,
            'name' => 'user'
        ]);

        Auth::login($user);

        $instances = ['cart1' => null, 'cart2' => null];

        foreach ($instances as $cartName => $content) {
            $cart = $this->getCart()->instance($cartName);

            $cart->add(new BuyableProduct);

            $instances[$cartName] = serialize($cart->content());
        }

        Auth::logout();

        foreach ($instances as $cartName => $content) {
            $this->assertDatabaseHas('shoppingcart', ['identifier' => $user->id, 'instance' => $cartName, 'content' => $content]);
        }
        Auth::login($user);

        foreach ($instances as $cartName => $content) {
            $this->assertDatabaseHas('shoppingcart', ['identifier' => $user->id, 'instance' => $cartName, 'content' => $content]);
        }
    }

    public function test_it_can_update_the_cart_in_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $serialized = serialize($cart->content());

        $this->assertDatabaseHas('shoppingcart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

        Event::assertDispatched('cart.stored');
    }

    public function test_it_can_restore_a_cart_from_the_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);

        $cart->restore($identifier);

        $this->assertItemsInCart(1, $cart);

        Event::assertDispatched('cart.restored');
    }

    public function test_it_will_just_keep_the_current_instance_if_no_cart_with_the_given_identifier_was_stored()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        $cart = $this->getCart();

        $cart->restore($identifier = 123);

        $this->assertItemsInCart(0, $cart);
    }

    public function test_it_can_get_all_instances()
    {
        $cart = $this->getCart();

        $cart->instance('cart1')->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->instance('cart2')->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals(['cart1', 'cart2'], $cart->getInstances());
    }

    public function test_it_can_calculate_all_values()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $this->assertEquals(10.00, $cartItem->price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());

        $this->assertEquals(20.00, $cart->subtotal());
        $this->assertEquals(23.80, $cart->total());
        $this->assertEquals(3.80, $cart->tax());
    }

    public function test_it_will_destroy_the_cart_when_the_user_logs_out_and_the_config_setting_was_set_to_true()
    {
        $this->app['config']->set('cart.destroy_on_logout', true);

        $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock) {
            $mock->shouldReceive('forget')->once()->with('cart');
        }));

        $user = Mockery::mock(Authenticatable::class);

        event(new Logout('web', $user));
    }

    public function test_it_can_add_a_valid_percentage_coupon()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage');
        $cart->addCoupon($coupon);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(9.00, $cartItem->price());
        $this->assertEquals(10.71, $cartItem->priceTax());
        $this->assertEquals(18.00, $cartItem->subtotal());
        $this->assertEquals(21.42, $cartItem->total());
        $this->assertEquals(1.71, $cartItem->tax());
        $this->assertEquals(3.42, $cartItem->taxTotal());
        $this->assertEquals(2.38, $cartItem->lineDiscount());

        $this->assertEquals(18.00, $cart->subtotal());
        $this->assertEquals(21.42, $cart->total());
        $this->assertEquals(3.42, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
    }

    public function test_it_can_add_a_valid_value_coupon()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $coupon = new CartCoupon('4.95Off', 4.95, 'value');
        $cart->addCoupon($coupon);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(10.00, $cartItem->price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());
        $this->assertEquals(0.00, $cartItem->lineDiscount());

        $this->assertEquals(20.00, $cart->subtotal());
        $this->assertEquals(18.85, $cart->total());
        $this->assertEquals(3.80, $cart->tax());
        $this->assertEquals(4.95, $cart->cartDiscount());
    }

    public function test_it_can_add_a_valid_value_and_percentage_coupon()
    {
        config(['cart.coupon.allow_multiple' => true]);

        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $coupon = new CartCoupon('4.95Off', 4.95, 'value');
        $cart->addCoupon($coupon);

        $coupon = new CartCoupon('10off', 0.1, 'percentage');
        $cart->addCoupon($coupon);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(9.00, $cartItem->price());
        $this->assertEquals(10.71, $cartItem->priceTax());
        $this->assertEquals(18.00, $cartItem->subtotal());
        $this->assertEquals(21.42, $cartItem->total());
        $this->assertEquals(1.71, $cartItem->tax());
        $this->assertEquals(3.42, $cartItem->taxTotal());
        $this->assertEquals(2.38, $cartItem->lineDiscount());

        $this->assertEquals(18.00, $cart->subtotal());
        $this->assertEquals(16.47, $cart->total());
        $this->assertEquals(3.42, $cart->tax());
        $this->assertEquals(4.95, $cart->cartDiscount());
    }

    public function test_allow_only_one_coupon_if_multiple_disabled_in_config_percentage()
    {
        config(['cart.coupon.allow_multiple' => false]);
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $coupon = new CartCoupon('20off', 0.2, 'percentage');
        $cart->addCoupon($coupon);

        $coupon = new CartCoupon('10off', 0.1, 'percentage');
        $cart->addCoupon($coupon);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(9.00, $cartItem->price());
        $this->assertEquals(10.71, $cartItem->priceTax());
        $this->assertEquals(18.00, $cartItem->subtotal());
        $this->assertEquals(21.42, $cartItem->total());
        $this->assertEquals(1.71, $cartItem->tax());
        $this->assertEquals(3.42, $cartItem->taxTotal());
        $this->assertEquals(2.38, $cartItem->lineDiscount());

        $this->assertEquals(18.00, $cart->subtotal());
        $this->assertEquals(21.42, $cart->total());
        $this->assertEquals(3.42, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
    }

    public function test_allow_only_one_coupon_if_multiple_disabled_in_config_value()
    {
        config(['cart.coupon.allow_multiple' => false]);
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $coupon = new CartCoupon('20off', 20, 'value');
        $cart->addCoupon($coupon);

        $coupon = new CartCoupon('4.95off', 4.95, 'value');
        $cart->addCoupon($coupon);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(10.00, $cartItem->price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());
        $this->assertEquals(0.00, $cartItem->lineDiscount());

        $this->assertEquals(20.00, $cart->subtotal());
        $this->assertEquals(18.85, $cart->total());
        $this->assertEquals(3.80, $cart->tax());
        $this->assertEquals(4.95, $cart->cartDiscount());
    }

    /**
     * Get an instance of the cart.
     *
     * @return \Mrkatz\Shoppingcart\Cart
     */
    private function getCart()
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Cart($session, $events);
    }

    /**
     * Set the config number format.
     * 
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     */
    private function setConfigFormat($decimals, $decimalPoint, $thousandSeperator)
    {
        $this->app['config']->set('cart.format.decimals', $decimals);
        $this->app['config']->set('cart.format.decimal_point', $decimalPoint);
        $this->app['config']->set('cart.format.thousand_seperator', $thousandSeperator);
    }
}
