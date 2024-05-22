<?php

namespace Mrkatz\Tests\Shoppingcart\Feature;

use Mrkatz\Shoppingcart\CartCoupon;
use Mrkatz\Shoppingcart\CartFee;
use Mrkatz\Shoppingcart\Exceptions\CouponException;
use Mrkatz\Shoppingcart\Facades\Cart;
use Mrkatz\Tests\Shoppingcart\Fixtures\BuyableProduct;
use Mrkatz\Tests\Shoppingcart\TestCase;

class ShoppingCartCouponFeeTest extends TestCase
{
    public function test_it_can_dissable_coupons_via_config()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage');
        $cart->addCoupon($coupon);

        $this->assertEquals(10.00, $cartItem->unitPrice());
        $this->assertEquals(9.00, $cartItem->price()); //Discounts Applied
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
        $this->assertEquals(2.38, $cart->savings());

        $this->assertEquals(18.00, $cart->subtotal());
        $this->assertEquals(21.42, $cart->total());
        $this->assertEquals(3.42, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(2.38, $cart->savings());

        config(['cart.coupon.enable' => false]);

        $this->assertEquals(10.00, $cartItem->unitPrice());
        $this->assertEquals(10.00, $cartItem->price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());
        $this->assertEquals(0.00, $cartItem->lineDiscount());

        $this->assertEquals(20.00, $cart->subtotal());
        $this->assertEquals(23.80, $cart->total());
        $this->assertEquals(3.80, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(0.00, $cart->savings());
    }

    public function test_it_can_add_a_valid_percentage_coupon()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage');
        $cart->addCoupon($coupon);

        $this->assertEquals(10.00, $cartItem->unitPrice());
        $this->assertEquals(9.00, $cartItem->price()); //Discounts Applied
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
        $this->assertEquals(2.38, $cart->savings());
    }

    public function test_it_will_discount_comparePrice_to_price_with_coupon()
    {
        $this->setConfigFormat(2, '.', '');
        config(['cart.compare_price.discount' => true]);

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', ['price' => 10.00, 'comparePrice' => 20.00]), 2);
        $cartItem->setTaxRate(19);

        $cartItem2 = $cart->add(new BuyableProduct(1, 'First item', ['price' => 7.00, 'comparePrice' => 8.00]), 1);
        $cartItem2->setTaxRate(19);

        $this->assertEquals(20.00, $cartItem->unitPrice());
        $this->assertEquals(10.00, $cartItem->Price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());
        $this->assertEquals(23.80, $cartItem->lineDiscount());

        $this->assertEquals(8.00, $cartItem2->unitPrice());
        $this->assertEquals(7.00, $cartItem2->Price());
        $this->assertEquals(8.33, $cartItem2->priceTax());
        $this->assertEquals(7.00, $cartItem2->subtotal());
        $this->assertEquals(8.33, $cartItem2->total());
        $this->assertEquals(1.33, $cartItem2->tax());
        $this->assertEquals(1.33, $cartItem2->taxTotal());
        $this->assertEquals(1.19, $cartItem2->lineDiscount());

        $this->assertEquals(27.00, $cart->subtotal());
        $this->assertEquals(32.13, $cart->total());
        $this->assertEquals(5.13, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(24.99, $cart->savings());
    }

    public function test_it_can_discount_comparePrice_to_price_with_coupon_per_item()
    {
        $this->setConfigFormat(2, '.', '');
        config(['cart.compare_price.discount' => false]);

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', ['price' => 10.00, 'comparePrice' => 20.00]), 2);
        $cartItem->setTaxRate(19);
        $cartItem->addCouponType('comparePrice');

        $cartItem2 = $cart->add(new BuyableProduct(1, 'First item', ['price' => 20.00, 'comparePrice' => 30.00]), 1);
        $cartItem2->setTaxRate(19);

        $this->assertEquals(20.00, $cartItem->unitPrice());
        $this->assertEquals(10.00, $cartItem->Price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());
        $this->assertEquals(23.80, $cartItem->lineDiscount());

        $this->assertEquals(20.00, $cartItem2->unitPrice());
        $this->assertEquals(20.00, $cartItem2->Price());
        $this->assertEquals(23.80, $cartItem2->priceTax());
        $this->assertEquals(20.00, $cartItem2->subtotal());
        $this->assertEquals(23.80, $cartItem2->total());
        $this->assertEquals(3.80, $cartItem2->tax());
        $this->assertEquals(3.80, $cartItem2->taxTotal());
        $this->assertEquals(0.00, $cartItem2->lineDiscount());

        $this->assertEquals(40.00, $cart->subtotal());
        $this->assertEquals(47.60, $cart->total());
        $this->assertEquals(7.60, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(23.80, $cart->savings());
    }

    public function test_it_will_validate_the_coupon_against_option_minimum_spend()
    {
        $this->expectException(CouponException::class);
        $this->expectExceptionMessage('Minimum Spend not Reached');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 1);
        $cartItem->setTaxRate(19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage', ['minimum_spend' => 20.00]);
        $cart->addCoupon($coupon);
    }

    public function test_it_will_validate_the_coupon_against_option_expired()
    {
        $this->expectException(CouponException::class);
        $this->expectExceptionMessage('Coupon Expired');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 1);
        $cart->setTax($cartItem->rowId, 19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage', ['end_date' => now()->addDay(-1)]);
        $cart->addCoupon($coupon);
    }

    public function test_it_will_validate_the_coupon_against_option_start_date()
    {
        $this->expectException(CouponException::class);
        $this->expectExceptionMessage('Coupon Not Valid');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 1);
        $cart->setTax($cartItem->rowId, 19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage', ['start_date' => now()->addDay(1)]);
        $cart->addCoupon($coupon);
    }

    public function test_it_will_validate_the_coupon_against_option_qty_required()
    {
        $this->expectException(CouponException::class);
        $this->expectExceptionMessage("Minimum QTY of 4 not Reached");

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 1);
        $cart->setTax($cartItem->rowId, 19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage', ['min_qty' => 4]);
        $cart->addCoupon($coupon);
    }

    public function test_it_will_validate_the_coupon_against_option_qty_required_happy()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 4);
        $cart->setTax($cartItem->rowId, 19);

        $coupon = new CartCoupon('10off', 0.1, 'percentage', ['min_qty' => 4]);
        $cart->addCoupon($coupon);

        $this->assertEquals(4, $cart->cartQty());

        $this->assertEquals('4.76', $cart->savings());
        $this->assertEquals('36.00', $cartItem->subtotal());
    }

    public function test_it_will_limit_the_coupon_against_option_max_discount()
    {
        $cartItem = Cart::add(new BuyableProduct(1, 'First item', 100.00), 1);
        $cartItem->setTaxRate(0);

        $coupon = new CartCoupon('20off', 0.2, 'percentage', ['max_discount' => 10]);
        Cart::addCoupon($coupon);

        $this->assertEquals(90.00, Cart::total());
        $this->assertEquals(10.00, Cart::savings());
        $cartItem->setQuantity(2);

        $this->assertEquals(190.00, Cart::total());
        $this->assertEquals(10.00, Cart::savings());
    }

    public function test_it_can_add_a_valid_value_coupon()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

        $coupon = new CartCoupon('4.95Off', 4.95, 'value');
        $cart->addCoupon($coupon);

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
        $this->assertEquals(4.95, $cart->savings());
    }

    public function test_it_can_add_a_valid_value_coupon_per_item()
    {
        $this->setConfigFormat(2, '.', '');

        $cartItem = Cart::instance()->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);
        $cartItem->addCouponType('value', ['value' => 4.95]);
        $this->assertEquals(10.00, $cartItem->price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());
        $this->assertEquals(0.00, $cartItem->lineDiscount());

        $this->assertEquals(20.00, Cart::instance()->subtotal());
        $this->assertEquals(18.85, Cart::total());
        $this->assertEquals(3.80, Cart::tax());
        $this->assertEquals(4.95, Cart::cartDiscount());
        $this->assertEquals(4.95, Cart::savings());
    }

    public function test_it_can_add_a_valid_value_and_percentage_coupon()
    {
        config(['cart.coupon.allow_multiple' => true]);

        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

        $coupon = new CartCoupon('4.95Off', 4.95, 'value');
        $cart->addCoupon($coupon);

        $coupon = new CartCoupon('10off', 0.1, 'percentage');
        $cart->addCoupon($coupon);

        $this->assertEquals(9.00, $cartItem->price());
        $this->assertEquals(10.71, $cartItem->priceTax());
        $this->assertEquals(18.00, $cartItem->subtotal());
        $this->assertEquals(21.42, $cartItem->total());
        $this->assertEquals(1.71, $cartItem->tax());
        $this->assertEquals(3.42, $cartItem->taxTotal());
        $this->assertEquals(2.38, $cartItem->lineDiscount());

        $this->assertEquals(18.00, $cart->subtotal());
        $this->assertEquals(16.47, $cart->total(true));
        $this->assertEquals(3.42, $cart->tax());
        $this->assertEquals(4.95, $cart->cartDiscount());
        $this->assertEquals(7.33, $cart->savings());
    }

    public function test_allow_only_one_coupon_if_multiple_disabled_in_config_percentage()
    {
        config(['cart.coupon.allow_multiple' => false]);
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

        $coupon = new CartCoupon('20off', 0.2, 'percentage');
        $cart->addCoupon($coupon);

        $coupon = new CartCoupon('10off', 0.1, 'percentage');
        $cart->addCoupon($coupon);

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
        $this->assertEquals(2.38, $cart->savings());
    }

    public function test_allow_only_one_coupon_if_multiple_disabled_in_config_value()
    {
        config(['cart.coupon.allow_multiple' => false]);
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

        $coupon = new CartCoupon('20off', 20, 'value');
        $cart->addCoupon($coupon);

        $coupon = new CartCoupon('4.95off', 4.95, 'value');
        $cart->addCoupon($coupon);

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
        $this->assertEquals(4.95, $cart->savings());
    }

    public function test_percentage_fee_can_be_added_to_cart()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem->setTaxRate(19);

        $fee = new CartFee('merchant', 'percentage', 0.05);
        $cart->addFee($fee);

        $this->assertEquals(10.00, $cartItem->price());
        $this->assertEquals(11.90, $cartItem->priceTax());
        $this->assertEquals(20.00, $cartItem->subtotal());
        $this->assertEquals(23.80, $cartItem->total());
        $this->assertEquals(1.90, $cartItem->tax());
        $this->assertEquals(3.80, $cartItem->taxTotal());
        $this->assertEquals(0.00, $cartItem->lineDiscount());

        $this->assertEquals(20.00, $cart->subtotal());
        $this->assertEquals(3.80, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(1.19, $cart->cartFees(true));
        $this->assertEquals(24.99, $cart->total(true));
        $this->assertEquals(0.00, $cart->savings());
    }

    public function test_value_fee_can_be_added_to_cart()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();
        $item1 = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $item1->setTaxRate(19);

        $fee = new CartFee('delivery', 'value', 30, ['taxable' => true]);
        $cartItem = $cart->addFee($fee);
        $cartItem->setTaxRate(19);

        $this->assertEquals(30.00, $cartItem->price());
        $this->assertEquals(35.70, $cartItem->priceTax());
        $this->assertEquals(30.00, $cartItem->subtotal());
        $this->assertEquals(35.70, $cartItem->total());
        $this->assertEquals(5.70, $cartItem->tax());
        $this->assertEquals(5.70, $cartItem->taxTotal());
        $this->assertEquals(0.00, $cartItem->lineDiscount());

        $this->assertEquals(50.00, $cart->subtotal());
        $this->assertEquals(9.50, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(0.00, $cart->cartFees(true));
        $this->assertEquals(59.50, $cart->total(true));
        $this->assertEquals(0.00, $cart->savings());
    }

    public function test_non_taxable_value_fee_can_be_added_to_cart()
    {
        $this->setConfigFormat(2, '.', '');

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct(1, 'First item', 10.00, 19), 2);
        $cartItem->setTaxRate(19);

        $fee = new CartFee('delivery', 'value', 30, ['taxRate' => 0]);
        $cartItem2 = $cart->addFee($fee);

        $this->assertEquals(30.00, $cartItem2->price());
        $this->assertEquals(30.00, $cartItem2->priceTax());
        $this->assertEquals(30.00, $cartItem2->subtotal());
        $this->assertEquals(30.00, $cartItem2->total());
        $this->assertEquals(0.00, $cartItem2->tax());
        $this->assertEquals(0.00, $cartItem2->taxTotal());
        $this->assertEquals(0.00, $cartItem2->lineDiscount());

        $this->assertEquals(50.00, $cart->subtotal());
        $this->assertEquals(3.80, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(0.00, $cart->cartFees(true));
        $this->assertEquals(53.80, $cart->total(true));
        $this->assertEquals(0.00, $cart->savings());
    }

    public function test_it_has_correct_values_without_discounts()
    {
        $this->setConfigFormat(2, '.', '');

        config(['cart.compare_price.default_multiplier' => 1.5]);

        $cart = $this->getCart();

        $cartItem1 = $cart->add(new BuyableProduct(1, 'First item', ['price' => 10.00, 'comparePrice' => 12.00]), 2);
        $cartItem1->setTaxRate(19);
        $cartItem2 = $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);
        $cartItem2->setTaxRate(19);

        $this->assertEquals(12.00, $cartItem1->comparePrice());
        $this->assertEquals(15.00, $cartItem2->comparePrice());
        $this->assertEquals(10.00, $cartItem1->unitPrice());
        $this->assertEquals(10.00, $cartItem1->price());
        $this->assertEquals(11.90, $cartItem1->priceTax());
        $this->assertEquals(20.00, $cartItem1->subtotal());
        $this->assertEquals(23.80, $cartItem1->total());
        $this->assertEquals(1.90, $cartItem1->tax());
        $this->assertEquals(3.80, $cartItem1->taxTotal());
        $this->assertEquals(0.00, $cartItem1->lineDiscount());

        $this->assertEquals(40.00, $cart->subtotal());
        $this->assertEquals(7.60, $cart->tax());
        $this->assertEquals(0.00, $cart->cartDiscount());
        $this->assertEquals(0.00, $cart->cartFees(true));
        $this->assertEquals(47.60, $cart->total(true));
        $this->assertEquals(0.00, $cart->savings());
    }
}
