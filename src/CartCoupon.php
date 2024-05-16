<?php

namespace Mrkatz\Shoppingcart;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Mrkatz\Shoppingcart\Exceptions\CouponException;

class CartCoupon extends Collection
{
    public $type;
    public $code;
    public $value;
    public $discounted = 0;
    public $appliedToCart = true;

    public $options = [];
    public $status = false; //true
    public $minimum_spend = null;
    public $maximum_spend = null;
    public $max_discount = null;
    public $start_date = null;
    public $end_date = null;
    public $min_qty = null;

    public $use_limit = null;
    public $multiple_use = false;
    public $total_use = null;
    public $validProducts = [];

    public function __construct($code, $value, $type = 'percentage', $options = [])
    {
        if (!in_array($type, ['percentage', 'value'])) $this->throwError('Invalid Coupon Type. Type should be "percentage" or "value"');
        if ($type === 'percentage' && $value > 1) $this->throwError('Invalid value for a percentage coupon. The value must be between 0 and 1.');

        $this->type = $type;
        $this->code = $code;
        $this->value = $value;

        $this->setOptions($options);
    }

    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    public function canApply(Cart $cart)
    {
        // dd('can apply?');
        // $this->message = 'Coupon Applied';

        //status
        //within Date
        //Min/Max Spend
        if ($cart->total(false) < $this->minimum_spend) return $this->throwError('Minimum Spend not Reached');
        //Max Discount
        //Use Expire
        if (isset($this->end_date)) {
            if (now()->gt($this->end_date)) return $this->throwError('Coupon Expired');
        }

        return true;
    }

    public function __get($option)
    {
        return Arr::get($this->options, $option);
    }

    public function throwError($message)
    {
        throw new CouponException($message);
    }

    protected function getConnection()
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    protected function getTableName()
    {
        return config('cart.database.table.coupons', 'coupons');
    }

    private function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }
}
