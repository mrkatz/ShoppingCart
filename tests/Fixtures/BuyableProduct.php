<?php

namespace Mrkatz\Tests\Shoppingcart\Fixtures;

use InvalidArgumentException;
use Mrkatz\Shoppingcart\Contracts\Buyable;

class BuyableProduct implements Buyable
{
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
     * BuyableProduct constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     */
    public function __construct($id = 1, $name = 'Item name', $price = 10.00)
    {
        $this->id = $id;
        $this->name = $name;

        if (is_array($price)) {
            $this->price = $price['price'];
            $this->comparePrice = $price['comparePrice'];
        } elseif (strlen($price) < 0 || !is_numeric($price)) {
            throw new InvalidArgumentException('Please supply a valid price.');
        } else {
            $this->price = floatval($price);
        }
    }

    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier($options = null)
    {
        return $this->id;
    }

    /**
     * Get the description or title of the Buyable item.
     *
     * @return string
     */
    public function getBuyableDescription($options = null)
    {
        return $this->name;
    }

    /**
     * Get the price of the Buyable item.
     *
     * @return float
     */
    public function getBuyablePrice($options = null)
    {
        return $this->price;
    }

    /**
     * Get the price of the Buyable item.
     *
     * @return float
     */
    public function getBuyableComparePrice($options = null)
    {
        return $this->comparePrice;
    }
}
