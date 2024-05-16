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
            $this->comparePrice = $price['comparePrice'];
        } elseif (strlen($price) < 0 || !is_numeric($price)) {
            throw new InvalidArgumentException('Please supply a valid price.');
        } else {
            $this->price = floatval($price);
        }
    }

    /**
     * Get Buyable settings/options.
     *
     * @return array
     */
    public function getBuyable($property = 'All')
    {
        $props = $this->getBuyableProps();

        switch ($property) {
            case 'All':
            default:
                return $property;
            case 'id':
            case 'name':
            case 'price':
            case 'comparePrice':
            case 'taxable':
            case 'taxRate':
                return $props[$property];
        }
    }

    /**
     * Get Buyable properties.
     *
     * @return array|mixed
     */
    public function getBuyableProps()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'comparePrice' => $this->comparePrice,

            'taxable' => $this->taxable,
            'taxRate' => $this->taxRate,

            'qty' => 1,
        ];
    }
}
