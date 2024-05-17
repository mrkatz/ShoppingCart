<?php

namespace Mrkatz\Tests\Shoppingcart\Fixtures;

use InvalidArgumentException;
use Mrkatz\Shoppingcart\Contracts\Buyable;
use Mrkatz\Shoppingcart\Traits\CanBeBought;

class BuyableProduct implements Buyable
{
    use CanBeBought;
    /**
     * @var int|string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var float
     */
    private $price;

    /**
     * @var float
     */
    private $comparePrice;

    /**
     * @var bool
     */
    private $taxable = true;

    /**
     * @var int
     */
    private $taxRate;

    /**
     * BuyableProduct constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     */
    public function __construct($id = 1, $name = 'Item name', $price = 10.00, $taxable = true)
    {
        $this->id = $id;
        $this->name = $name;

        if (is_int($taxable)) {
            $this->taxable = true;
            $this->taxRate = $taxable;
        } else {
            $this->taxable = $taxable;
            $this->taxRate = config('cart.tax');
        }

        if (is_array($price)) {
            $this->price = $price['price'];
            if (isset($price['comparePrice'])) {
                $this->comparePrice = $price['comparePrice'];
            } else {
                // $this->comparePrice = $this->price * config('cart.compare_price.default_multiplier', 1.3);
            }
        } elseif (strlen($price) < 0 || !is_numeric($price)) {
            throw new InvalidArgumentException('Please supply a valid price.');
        } else {
            $this->price = floatval($price);
        }
    }
    public function price($format)
    {
        return $this->price;
    }

    public function prependprice($format = true, $prepend = null)
    {
        $price = $format ? number_format($this->price, 2, '.', ',') : $this->price;
        if (isset($prepend)) return $prepend . $price;
        return $price;
    }

    public function taxable($format)
    {
        return $this->taxable;
    }

    public function comparePrice($format)
    {
        return $this->comparePrice;
    }
}
