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
    public $use_limit = null;
    public $multiple_use = false;
    public $total_use = null;


    public function __construct($code, $value, $type = 'percentage', $options = [])
    {
        if (!in_array($type, ['percentage', 'value'])) $this->throwError('Invalid Coupon Type. Type should be "percentage" or "value"');
        if ($type === '%' && $value > 1) $this->throwError('Invalid value for a percentage coupon. The value must be between 0 and 1.');

        $this->type = $type;
        $this->code = $code;
        $this->value = $value;

        $this->setOptions($options);
    }

    /**
     * Sets all the options for the coupon.
     *
     * @param $options
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Gets the discount amount.
     *
     * @return string
     */
    public function discount($price)
    {
        if ($this->canApply()) {
            $discount = $this->value - $this->discounted;
            if ($discount > $price) {
                return $price;
            }

            return $discount;
        }

        return 0;
    }

    /**
     * Checks to see if we can apply the coupon.
     *
     * @return bool
     */
    public function canApply(Cart $cart)
    {
        // $this->message = 'Coupon Applied';

        //status
        //within Date
        //Min/Max Spend
        //Max Discount
        //Use Expire
        return true;
    }

    /**
     * Displays the value in a money format.
     *
     * @param null $locale
     * @param null $currencyCode
     *
     * @return string
     */
    public function displayValue($locale = null, $currencyCode = null, $format = true)
    {
        // return Cart::formatMoney(
        //     $this->value,
        //     $locale,
        //     $currencyCode,
        //     $format
        // );
    }

    /**
     * Magic Method allows for user input as an object.
     *
     * @param $option
     *
     * @return mixed | null
     */
    public function __get($option)
    {
        return Arr::get($this->options, $option);
    }

    protected function throwError($message)
    {
        throw new CouponException($message);
    }

    /**
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getConnection()
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    protected function getTableName()
    {
        return config('cart.database.table.coupons', 'coupons');
    }

    /**
     * Get the database connection name.
     *
     * @return string
     */
    private function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }
}
