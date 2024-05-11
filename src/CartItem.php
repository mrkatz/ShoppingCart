<?php

namespace Mrkatz\Shoppingcart;

use Illuminate\Contracts\Support\Arrayable;
use Mrkatz\Shoppingcart\Contracts\Buyable;
use Illuminate\Contracts\Support\Jsonable;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class CartItem implements Arrayable, Jsonable
{
    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public $id;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $qty;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public $name;

    /**
     * The price without TAX of the cart item.
     *
     * @var float
     */
    public $price;

    /**
     * The compare price without TAX of the cart item.
     *
     * @var float
     */
    public $comparePrice;

    /**
     * The cart item applied coupons.
     *
     * @var array
     */
    public $coupons = [];

    protected $discount = 0;

    /**
     * The cart item applied fees.
     *
     * @var array
     */
    public $fees = [];

    /**
     * The options for this cart item.
     *
     * @var collection
     */
    public $options;

    /**
     * The FQN of the associated model.
     *
     * @var string|null
     */
    private $associatedModel = null;

    /**
     * The tax rate for the cart item.
     *
     * @var int|float
     */
    private $taxRate = 0;

    /**
     * Is item saved for later.
     *
     * @var boolean
     */
    private $isSaved = false;

    /**
     * CartItem constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float|array      $price
     * @param array      $options
     */
    public function __construct($id, $name, $price, array $options = [])
    {
        if (empty($id)) {
            throw new InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new InvalidArgumentException('Please supply a valid name.');
        }

        if (is_array($price)) {
            $this->price = $price['price'];
            $this->comparePrice = $price['comparePrice'];
        } elseif (strlen($price) < 0 || !is_numeric($price)) {
            throw new InvalidArgumentException('Please supply a valid price.');
        } else {
            $this->price    = floatval($price);
        }

        $this->id       = $id;
        $this->name     = $name;

        $this->options  = new CartItemOptions($options);
        $this->rowId = $this->generateRowId($id, $options);
    }

    /**
     * Returns the formatted price without TAX.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function price($format = false, $includeDiscount = true)
    {
        $price = $this->processCoupons('price', $includeDiscount);

        if ($format) return $this->format($price);
        return $price;
    }

    /**
     * Returns the formatted compare_price without TAX.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function comparePrice($format)
    {
        if ($format) return $this->format($this->comparePrice);
        return $this->comparePrice;
    }

    /**
     * Returns the formatted price with TAX.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function priceTax($format = false, $includeDiscount = true)
    {
        $priceTax = ($this->price(false, $includeDiscount) + $this->tax(false, $includeDiscount));

        if ($format) return $this->format($priceTax);
        return $priceTax;
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function subtotal($format = false, $includeDiscount = true)
    {
        $subtotal = ($this->qty * $this->price(false, $includeDiscount));

        if ($format) return $this->format($subtotal);
        return $subtotal;
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function total($format = false, $includeDiscount = true)
    {
        $total = ($this->qty * $this->priceTax(false, $includeDiscount));

        if ($format) return $this->format($total);
        return $total;
    }

    /**
     * Returns the formatted tax.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function tax($format = false, $includeDiscount = true)
    {
        $tax = $this->processCoupons('tax', $includeDiscount);

        if ($format) return $this->format($tax);
        return $tax;
    }

    /**
     * Returns the formatted tax.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    public function taxTotal($format = false, $includeDiscount = true)
    {
        $taxTotal = $this->processCoupons('taxTotal', $includeDiscount);

        if ($format) return $this->format($taxTotal);
        return $taxTotal;
    }

    public function lineDiscount()
    {
        $discount = $this->total(false, false) - $this->total();

        return $this->format($discount, 2);
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $qty
     */
    public function setQuantity($qty)
    {
        if (empty($qty) || !is_numeric($qty))
            throw new \InvalidArgumentException('Please supply a valid quantity.');

        $this->qty = $qty;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @param \Mrkatz\Shoppingcart\Contracts\Buyable $item
     * @return void
     */
    public function updateFromBuyable(Buyable $item)
    {
        $this->id       = $item->getBuyableIdentifier($this->options);
        $this->name     = $item->getBuyableDescription($this->options);
        $this->price    = $item->getBuyablePrice($this->options);
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     * @return void
     */
    public function updateFromArray(array $attributes)
    {
        $this->id       = Arr::get($attributes, 'id', $this->id);
        $this->qty      = Arr::get($attributes, 'qty', $this->qty);
        $this->name     = Arr::get($attributes, 'name', $this->name);
        $this->price    = Arr::get($attributes, 'price', $this->price);
        $this->options  = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    protected function processCoupons($prop, $includeDiscount = true)
    {
        if (!$includeDiscount || count($this->coupons) === 0) return $this->$prop;

        return $this->$prop * (1 - $this->discount);
    }

    public function addCoupon(CartCoupon $coupon)
    {
        if (!config('cart.coupon.allow_multiple')) {
            $this->coupons = [];
        }
        $this->coupons[$coupon->code] = $coupon;

        $this->discount = 0;
        foreach ($this->coupons as $coupon) {
            if ($coupon->type === 'percentage') {
                $this->discount += $coupon->value;
            }
        }
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     * @return \Mrkatz\Shoppingcart\CartItem
     */
    public function associate($model)
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);

        return $this;
    }

    /**
     * Set the tax rate.
     *
     * @param int|float $taxRate
     * @return \Mrkatz\Shoppingcart\CartItem
     */
    public function setTaxRate($taxRate)
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    /**
     * Set saved state.
     *
     * @param bool $bool
     * @return \Mrkatz\Shoppingcart\CartItem
     */
    public function setSaved($bool)
    {
        $this->isSaved = $bool;

        return $this;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     * @method string priceTax
     * @param string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if ($attribute === 'priceTax') {
            return ($this->price() + $this->tax());
        }

        if ($attribute === 'subtotal') {
            return ($this->qty * $this->price());
        }

        if ($attribute === 'total') {
            return ($this->qty * $this->priceTax);
        }

        if ($attribute === 'tax') {
            return ($this->price * ($this->taxRate / 100));
        }

        if ($attribute === 'taxTotal') {
            return ($this->tax * $this->qty);
        }

        if ($attribute === 'model' && isset($this->associatedModel)) {
            return with(new $this->associatedModel)->find($this->id);
        }

        return null;
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param \Mrkatz\Shoppingcart\Contracts\Buyable $item
     * @param array                                      $options
     * @return \Mrkatz\Shoppingcart\CartItem
     */
    public static function fromBuyable(Buyable $item, array $options = [])
    {
        return new self($item->getBuyableIdentifier($options), $item->getBuyableDescription($options), ['price' => $item->getBuyablePrice($options), 'comparePrice' => $item->getBuyableComparePrice($options)], $options);
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     * @return \Mrkatz\Shoppingcart\CartItem
     */
    public static function fromArray(array $attributes)
    {
        $options = Arr::get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param array      $options
     * @return \Mrkatz\Shoppingcart\CartItem
     */
    public static function fromAttributes($id, $name, $price, array $options = [])
    {
        return new self($id, $name, $price, $options);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $id
     * @param array  $options
     * @return string
     */
    protected function generateRowId($id, array $options)
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'price'    => $this->price,
            'options'  => $this->options->toArray(),
            'tax'      => $this->tax(true),
            'isSaved'      => $this->isSaved,
            'subtotal' => $this->subtotal(true)
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        if (isset($this->associatedModel)) {

            return json_encode(array_merge($this->toArray(), ['model' => $this->model]), $options);
        }

        return json_encode($this->toArray(), $options);
    }

    public function format($value, $decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        if (is_string($value)) {
            return $this->numberFormat($this->$value, $decimals, $decimalPoint, $thousandSeperator);
        }

        return $this->numberFormat($value, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the formatted number.
     *
     * @param float  $value
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     * @return string
     */
    private function numberFormat($value, $decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        if (is_null($decimals)) {
            $decimals = is_null(config('cart.format.decimals')) ? 2 : config('cart.format.decimals');
        }

        if (is_null($decimalPoint)) {
            $decimalPoint = is_null(config('cart.format.decimal_point')) ? '.' : config('cart.format.decimal_point');
        }

        if (is_null($thousandSeperator)) {
            $thousandSeperator = is_null(config('cart.format.thousand_seperator')) ? ',' : config('cart.format.thousand_seperator');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeperator);
    }
}
