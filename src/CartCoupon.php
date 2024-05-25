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
    public $status = true; //false
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
        if (!in_array($type, ['percentage', 'value', 'comparePrice'])) $this->throwError('Invalid Coupon Type. Type should be "percentage" or "value"');
        if ($type === 'percentage' && $value > 1) $this->throwError('Invalid value for a percentage coupon. The value must be between 0 and 1.');
        if (!is_numeric($value) && !($type === 'comparePrice')) $this->throwError('Invalid value for coupon. - ' . $value);

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
        //Required QTY
        if (!$this->satisfiesMinQty($cart->cartQty())) return $this->throwError("Minimum QTY of $this->min_qty not Reached");
        //status
        if (!$this->isValid()) return $this->throwError('Coupon Not Valid');
        //MinSpend
        if (!$this->satisfiesMinSpend($cart->total(false))) return $this->throwError('Minimum Spend not Reached');

        if (!$this->satisfiesStartDate()) return $this->throwError('Coupon Not Valid');
        //Use Expire
        if (!$this->satisfiesEndDate()) return $this->throwError('Coupon Expired');

        return true;
    }

    public function isValid()
    {
        return $this->status;
    }

    public function hasMaxDiscount()
    {
        return isset($this->max_discount);
    }

    public function satisfiesMinSpend($total)
    {
        return is_null($this->minimum_spend) || $total >= $this->minimum_spend;
    }

    public function satisfiesMinQty($qty)
    {
        return is_null($this->min_qty) || $qty >= $this->min_qty;
    }

    public function satisfiesStartDate()
    {
        return is_null($this->start_date) || now()->gt($this->start_date);
    }

    public function satisfiesEndDate()
    {
        return is_null($this->end_date) || !now()->gt($this->end_date);
    }

    public function satisfiesProductRestriction($rowId = null)
    {
        return count($this->validProducts) === 0 || (!is_null($rowId) && in_array($rowId, $this->validProducts));
    }

    public function isPercentage()
    {
        return $this->type === 'percentage';
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
