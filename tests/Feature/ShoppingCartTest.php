<?php

namespace Mrkatz\Tests\Shoppingcart\Feature;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Mockery;
// use Mrkatz\Shoppingcart\Cart;
use Mrkatz\Shoppingcart\CartItem;
use Mrkatz\Shoppingcart\Exceptions\ConfigError;
use Mrkatz\Shoppingcart\Exceptions\InvalidRowIDException;
use Mrkatz\Shoppingcart\Exceptions\UnknownModelException;
use Mrkatz\Tests\Shoppingcart\TestCase;
use Mrkatz\Shoppingcart\Facades\Cart;
use Mrkatz\Tests\Shoppingcart\Fixtures\BuyableProduct;
use Mrkatz\Tests\Shoppingcart\Fixtures\ProductModel;
use Mrkatz\Tests\Shoppingcart\Fixtures\TestUser;

class ShoppingCartTest extends TestCase
{
    public function test_it_can_add_an_item_to_the_cart()
    {
        Event::fake();
        $cartItem = Cart::add('item123', 'Sample Item', 1, 9.99);
        $this->assertCount(1, Cart::content());
        $this->assertEquals('item123', $cartItem->id);
        $this->assertEquals('Sample Item', $cartItem->name);
        $this->assertEquals(1, $cartItem->qty);
        $this->assertEquals(9.99, $cartItem->price);
        Event::assertDispatched('cart.added');
    }

    public function test_it_can_handle_price_as_string_and_seperator()
    {
        $this->setConfigFormat(2, '.', ',');
        $cart = $this->getCart();

        $cartItem =   $cart->add(new BuyableProduct(
            1,
            'Some title',
            ['price' => number_format(1311.82, 2, '.', ','), 'comparePrice' => number_format(1311.82 * 1.3, 2, '.', ',')]
        ), 1);
        $cartItem->setTaxRate(10);

        $this->assertEquals(131.18, $cartItem->tax());
        $this->assertEquals('Some title', $cartItem->name);
        $this->assertEquals(1, $cartItem->qty);
        $this->assertEquals(1311.82, $cartItem->price(false));
        $this->assertEquals(1705.37, $cartItem->comparePrice(false));
        $this->assertEquals('1,311.82', $cartItem->price(true));
        $this->assertEquals('1,705.37', $cartItem->comparePrice(true));
        $this->assertEquals('1,443.00', $cart->total());
        $this->assertEquals(1443.002, $cart->total(false));
    }

    public function test_it_can_handle_price_passed_in_as_array_without_compare_price()
    {
        config(['cart.compare_price.default_multiplier' => 1.5]);
        $this->setConfigFormat(2, '.', ',');
        $cart = $this->getCart();

        $cartItem =   $cart->add(new BuyableProduct(1, 'Some title', ['price' => 1311.82]), 1);
        $cartItem->setTaxRate(10);

        $this->assertEquals(131.18, $cartItem->tax());
        $this->assertEquals('Some title', $cartItem->name);
        $this->assertEquals(1, $cartItem->qty);
        $this->assertEquals(1311.82, $cartItem->price(false));
        $this->assertEquals('1,311.82', $cartItem->price(true));
        $this->assertEquals('1,967.73', $cartItem->comparePrice(true));
        $this->assertEquals('1,443.00', $cart->total());
        $this->assertEquals(1443.002, $cart->total(false));
    }

    public function test_handle_config_compare_price_default_multiplier_settings()
    {
        $this->expectException(ConfigError::class);
        $this->expectExceptionMessage('Please Check ComparePrice Multiplier.');

        config(['cart.compare_price.default_multiplier' => 'null']);
        $cart = $this->getCart();

        $cartItem =   $cart->add(new BuyableProduct(1, 'Some title', ['price' => 1311.82]), 1);
        $cartItem->setTaxRate(10);

        $this->assertEquals(131.18, $cartItem->tax());
        $this->assertEquals('Some title', $cartItem->name);
        $this->assertEquals(1, $cartItem->qty);
        $this->assertEquals(1311.82, $cartItem->price(false));
        $this->assertEquals('1,311.82', $cartItem->price(true));
        $this->assertEquals('1,967.73', $cartItem->comparePrice(true));
        $this->assertEquals('1,443.00', $cart->total());
        $this->assertEquals(1443.002, $cart->total(false));
    }

    public function test_it_has_a_default_instance()
    {
        $cart = $this->getCart();

        $this->assertEquals(config('cart.instances.default', 'default'), $cart->currentInstance());
    }

    public function test_current_instance_can_be_retreaved_statically()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item'));

        $this->assertEquals($cartItem, Cart::get($cartItem->rowId));
    }

    public function test_it_can_have_multiple_instances()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item'));

        $cart->instance('wishlist')->add(new BuyableProduct(2, 'Second item'));

        $this->assertItemsInCart(1, $cart->instance(config('cart.instances.default', 'default')));
        $this->assertItemsInCart(1, $cart->instance('wishlist'));
    }

    public function test_it_will_return_the_cartitem_of_the_added_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $this->assertInstanceOf(CartItem::class, $cartItem);

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

    public function test_it_can_add_an_item_with_an_option_from_attributes()
    {
        Event::fake();

        $cart = $this->getCart();

        $item = $cart->add(1, 'Test item', 1, 10.00, ['taxRate' => 10]);

        $this->assertEquals(1, $cart->count());

        $this->assertEquals('1.00', $item->tax);

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_multiple_buyable_items_at_once()
    {
        Event::fake();

        Cart::add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertEquals(2, Cart::count());

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

        $cartItem = $cart->add(new BuyableProduct, 1, $options);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('XL', $cartItem->options->size);
        $this->assertEquals('red', $cartItem->options->color);

        Event::assertDispatched('cart.added');
    }

    public function test_it_will_validate_the_identifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid identifier.');

        $cart = $this->getCart();

        $cart->add(null, 'Some title', 1, 10.00);
    }

    public function test_it_will_validate_the_name()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid name.');
        $cart = $this->getCart();

        $cart->add(1, null, 1, 10.00);
    }

    public function test_it_will_validate_the_quantity()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid quantity.');
        $cart = $this->getCart();

        $cart->add(1, 'Some title', 'invalid', 10.00);
    }

    public function test_it_will_validate_the_price()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid price.');
        $cart = $this->getCart();

        $cart->add(1, 'Some title', 1, 'invalid');
    }

    public function test_it_will_update_the_cart_qty_if_the_item_already_exists_in_the_cart()
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

        $cartItem = $cart->add(new BuyableProduct);

        $cart->update($cartItem->rowId, 2);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        Event::assertDispatched('cart.updated');
    }

    public function test_it_can_update_an_existing_item_in_the_cart_from_a_buyable()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);
        $cartUpdated = $cart->update($cartItem->rowId, new BuyableProduct(1, 'Different description'));

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get($cartItem->rowId)->name);
        $this->assertEquals($cartItem->rowId, $cartUpdated->rowId);
        Event::assertDispatched('cart.updated');
    }

    public function test_it_can_update_an_existing_item_in_the_cart_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $cart->update($cartItem->rowId, ['name' => 'Different description']);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get($cartItem->rowId)->name);

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

        $cartItem1 = $this->rowId($cart->add(new BuyableProduct, 1, ['color' => 'red']));
        $cartItem2 = $this->rowId($cart->update($cartItem1, ['options' => ['color' => 'blue']]));

        $this->assertNotEquals($cartItem1, $cartItem2);
        $this->assertItemsInCart(1, $cart);
        $this->assertEquals($cartItem2, $cart->content()->first()->rowId);
        $this->assertEquals('blue', $cart->get($cartItem2)->options->color);
    }

    public function test_it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_rowid()
    {
        $cart = $this->getCart();

        $action1 = $this->rowId($cart->add(new BuyableProduct, 1, ['color' => 'red']));
        $action2 = $this->rowId($cart->add(new BuyableProduct, 1, ['color' => 'blue']));
        $this->assertEquals($action1, $action2);

        $action3 = $this->rowId($cart->update($action1, ['options' => ['color' => 'red']]));

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    public function test_it_can_remove_an_item_from_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $cart->remove($cartItem);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_will_remove_the_item_if_its_quantity_was_set_to_zero()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $cart->update($cartItem, 0);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_will_remove_the_item_if_its_quantity_was_set_negative()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $cart->update($cartItem, -1);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_can_get_an_item_from_the_cart_by_its_rowid()
    {
        $cart = $this->getCart();

        $item = $this->rowId($cart->add(new BuyableProduct));

        $cartItem = $cart->get($item);

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
        config(['cart.compare_price.default_multiplier' => 1.3]);
        config(['cart.tax' => 21]);

        $cart = $this->getCart();

        $cartItem1 = $cart->add(new BuyableProduct(1));
        $cartItem2 = $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertEquals([
            $cartItem1->rowId => [
                'rowId' => $cartItem1->rowId,
                'associatedModel' => $cartItem1->associatedModel,
                'id' => 1,
                'name' => 'Item name',
                'qty' => 1,
                'comparePrice' => 13.0,
                'unitPrice' => 10.0,
                'price' => 10.0,
                'coupons' => [],
                'discount' => 0,
                'fees' => [],
                'taxable' => true,
                'taxRate' => 21,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'isSaved' => false,
                'options' => [],
            ],
            $cartItem2->rowId => [
                'rowId' => $cartItem2->rowId,
                'associatedModel' => $cartItem2->associatedModel,
                'id' => 2,
                'name' => 'Item name',
                'qty' => 1,
                'comparePrice' => 13.0,
                'unitPrice' => 10.0,
                'price' => 10.0,
                'coupons' => [],
                'discount' => 0,
                'fees' => [],
                'taxable' => true,
                'taxRate' => 21,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'isSaved' => false,
                'options' => [],
            ],
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
        $this->setConfigFormat(2, ',', '.');
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 1000.00));
        $cart->add(new BuyableProduct(2, 'Second item', 2500.00), 2);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals('6.000,00', $cart->subtotal(true));
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

        $cartItem = $cart->add(new BuyableProduct);

        $this->assertEquals(BuyableProduct::class, $cartItem->associatedModel);
    }

    public function test_it_can_associate_the_cart_item_with_a_model()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate($cartItem, new ProductModel);

        $this->assertEquals(ProductModel::class, $cartItem->associatedModel);
    }

    public function test_it_will_throw_an_exception_when_a_non_existing_model_is_being_associated()
    {
        $this->expectException(UnknownModelException::class);
        $this->expectExceptionMessage('The supplied model SomeModel does not exist');

        $cart = $this->getCart();

        $cartItem = $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate($cartItem->rowId, 'SomeModel');
    }

    public function test_it_can_get_the_associated_model_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate($cartItem, new ProductModel);

        $this->assertInstanceOf(ProductModel::class, $cartItem->model);
        $this->assertEquals('Some value', $cartItem->model->someValue);
    }

    public function test_it_can_calculate_the_subtotal_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', 9.99), 3);

        $this->assertEquals(29.97, $cartItem->subtotal);
    }

    public function test_it_can_return_a_formatted_subtotal()
    {
        $this->setConfigFormat(2, ',', '.');
        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', 500), 3);

        $this->assertEquals('1.500,00', $cartItem->subtotal(true));
    }

    public function test_it_can_calculate_tax_based_on_the_default_tax_rate_in_the_config()
    {
        config(['cart.tax' => 21]);

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $this->assertEquals(2.10, $cartItem->tax);
    }

    public function test_it_can_set_tax_based_on_passed_in_options()
    {
        config(['cart.tax' => 21]);

        $cart = $this->getCart();

        $cartItem =  $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $this->assertEquals(2.10, $cartItem->tax);
    }

    public function test_it_can_calculate_tax_based_on_the_specified_tax_via_cart()
    {
        $cart = $this->getCart();

        $cartItem =   $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cart->setTax($cartItem, 19);

        $this->assertEquals(1.90, $cartItem->tax);
    }

    public function test_it_can_calculate_tax_based_on_the_specified_tax_via_cartItem()
    {
        $cart = $this->getCart();

        $cartItem =   $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cartItem->setTaxRate(19);

        $this->assertEquals(1.90, $cartItem->tax);
    }

    public function test_it_can_return_the_calculated_tax_formatted()
    {
        $this->setConfigFormat(2, ',', '.');
        $cart = $this->getCart();

        $cartItem =  $cart->add(new BuyableProduct(1, 'Some title', 10000.00), 1);

        $this->assertEquals('2.100,00', $cartItem->tax(true));
    }

    public function test_it_can_calculate_the_total_tax_for_all_cart_items()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(10.50, $cart->tax(false));
    }

    public function test_it_can_return_formatted_total_tax()
    {
        $this->setConfigFormat(2, ',', '.');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('1.050,00', $cart->tax(true));
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
        $this->setConfigFormat(2, ',', '');
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal(true));
    }

    public function test_it_can_set_and_retreave_comparePrice()
    {
        $this->setConfigFormat(2, ',', '');
        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', ['price' => 1000.00, 'comparePrice' => 2200.00]), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal(true));
        $this->assertEquals('5000.00', $cart->subtotal);

        $this->assertEquals('2200,00', $cartItem->comparePrice());
        $this->assertEquals('2200.00', $cartItem->comparePrice);
    }

    public function test_it_can_set_comparePrice_automatically_via_config()
    {
        $this->setConfigFormat(2, ',', '');
        config(['cart.compare_price.default_multiplier' => 1.3]);
        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $this->assertEquals('4000,00', $cart->subtotal());
        $this->assertEquals('4000.00', $cart->subtotal);

        $this->assertEquals('2600,00', $cartItem->comparePrice());
        $this->assertEquals('2600.00', $cartItem->comparePrice);
    }

    public function test_it_can_get_compare_price_for_entire_cart()
    {
        $this->setConfigFormat(2, ',', '');
        config(['cart.compare_price.default_multiplier' => 1.3]);
        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $this->assertEquals('4000,00', $cart->subtotal());
        $this->assertEquals('4000.00', $cart->subtotal);

        $this->assertEquals('2600,00', $cartItem->comparePrice());
        $this->assertEquals('2600.00', $cartItem->comparePrice);

        $this->assertEquals('5200,00', $cart->comparePrice());
    }

    public function test_it_will_grab_price_without_discount_as_comparePrice_if_not_set()
    {
        $this->setConfigFormat(2, ',', '');
        config(['cart.compare_price.default_multiplier' => 0]);
        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $this->assertEquals('4000,00', $cart->subtotal());
        $this->assertEquals('4000.00', $cart->subtotal);

        $this->assertEquals('2000,00', $cartItem->comparePrice());
        $this->assertEquals(null, $cartItem->comparePrice);
    }

    public function test_it_can_return_cart_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal(true));
        $this->assertEquals('1050,00', $cart->tax(true));
        $this->assertEquals('6050,00', $cart->total(true));

        $this->assertEquals(5000.0, $cart->subtotal);
        $this->assertEquals('1050.00', $cart->tax);
        $this->assertEquals('6050.00', $cart->total);
    }

    public function test_it_can_return_cartItem_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $this->assertEquals('2000,00', $cartItem->price());
        $this->assertEquals('2420,00', $cartItem->priceTax());
        $this->assertEquals('4000,00', $cartItem->subtotal());
        $this->assertEquals('4840,00', $cartItem->total());
        $this->assertEquals('420,00', $cartItem->tax());
        $this->assertEquals('840,00', $cartItem->taxTotal());
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

        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

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
}
