<?php

use Mrkatz\Shoppingcart\Exceptions\BuyableException;
use Mrkatz\Tests\Shoppingcart\Fixtures\BuyableProduct;

it('can get buyable properties', function () {
    $product = new BuyableProduct();

    expect($product->price(true))->toBe(10.00);
    expect($product->taxable(true))->toBe(true);
});

it('can get all buyable properties', function () {

    $product = new BuyableProduct();

    $props = $product->getBuyableProps();

    expect($props)->toBeArray();
    expect($props)->toHaveKeys(['id', 'name', 'price', 'comparePrice', 'taxable', 'taxRate']);
});

it('can resolve buyable values from configuration', function () {
    config([
        'cart.buyable.model.' . BuyableProduct::class . '.comparePrice' => '{15.00}',
        'cart.buyable.model.' . BuyableProduct::class . '.price' => 'prependPrice(true,$)'
    ]);

    $product = new BuyableProduct();
    expect($product->getBuyable('id'))->toBe(1);
    expect($product->getBuyable('name'))->toBe('Item name');
    expect($product->getBuyable('price'))->toBe('$10.00');
    expect($product->getBuyable('comparePrice'))->toBe('15.00');
    expect($product->getBuyable('taxable'))->toBe(true);
    expect($product->getBuyable('taxRate'))->toBe(config('cart.tax'));
});

it('expects Exception if config not setup', function () {
    config(['cart.buyable.model' => []]);

    $product = new BuyableProduct();
    $product->getBuyable('id');
})->throws(BuyableException::class, 'Buyable id not Set');
