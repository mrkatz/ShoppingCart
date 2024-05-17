<?php

namespace Mrkatz\Shoppingcart;

use Illuminate\Contracts\Support\Arrayable;
use Mrkatz\Shoppingcart\Contracts\Buyable;
use Illuminate\Contracts\Support\Jsonable;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Mrkatz\Shoppingcart\Exceptions\ConfigError;
use Mrkatz\Shoppingcart\Facades\Cart;
use Mrkatz\Shoppingcart\Traits\HasOptions;

use function PHPUnit\Framework\returnSelf;

class CartItem implements Arrayable, Jsonable
{
    public $rowId;
    private $associatedModel = null;

    public $id;
    public $name;
    public $qty;

    public $comparePrice;
    public $unitPrice;
    public $price;
    //tax
    //subTotal
    //priceTax
    //taxTotal
    //Total
    public $coupons = [];
    protected $discount = 0; //% Value UNit Total
    public $fees = [];

    private $taxable = true;
    private $taxRate = 0;

    public $options;
    private $isSaved = false;

    public function __construct($id, $name, $price = null, array $options = [])
    {
        if (empty($id)) $this->throwError('Please supply a valid identifier.');
        if (empty($name)) $this->throwError('Please supply a valid name.');

        $addAutoCompareCoupon = false;

        if (config('cart.compare_price.discount', false)) {

            if (isset($price['comparePrice'])) {
                $comparePrice = $this->checkNumb($price['comparePrice']);
                $priceVal = $this->checkNumb($price['price']);
            } else {
                $comparePrice = $this->checkNumb($price) * $this->comparePrice_multiplier();
                $priceVal = $this->checkNumb($price);
            }

            $this->price = $comparePrice;
            $addAutoCompareCoupon = true;
        } else {

            if (is_array($price)) {
                $this->price = $this->checkNumb($price['price']);
                if (isset($price['comparePrice'])) {
                    $this->comparePrice = $this->checkNumb($price['comparePrice']);
                } else {
                    $this->comparePrice = $this->price * $this->comparePrice_multiplier();
                }
            } elseif (strlen($price) < 0 || !is_numeric($price)) {
                $this->throwError('Please supply a valid price.');
            } else {
                $this->price    = $this->checkNumb($price);
                $this->comparePrice = $this->price * $this->comparePrice_multiplier();
            }
        }

        $this->id       = $id;
        $this->name     = $name;

        $this->options  = new CartItemOptions(collect($options)->forget(['id', 'name', 'price', 'comparePrice']));

        $this->updateOptions();

        $this->rowId = $this->generateRowId($id, $options);

        if ($addAutoCompareCoupon) {

            $discountVal = 1 - ($priceVal / $comparePrice);
            $coupon = new CartCoupon(config('cart.compare_price.discount_code', today()->format('M') . 'only'), $discountVal, 'percentage', ['validProducts' => [$this->rowId]]);
            $this->addCoupon($coupon);
        }
    }

    protected function updateOptions()
    {
        $splitFromOptions = [];
        foreach ($this->options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->$option = $value;
                $splitFromOptions[] = $option;
            }
        };
        $this->options = new CartItemOptions(collect($this->options)->forget($splitFromOptions));
    }

    public function associate($model)
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);

        return $this;
    }

    public function setTaxRate($taxRate)
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    public function setQuantity($qty)
    {
        if (empty($qty) || !is_numeric($qty)) $this->throwError('Please supply a valid quantity.');

        $this->qty = $qty;
    }

    protected function processCoupons($prop, $includeDiscount = true)
    {
        if (!$includeDiscount || count($this->coupons) === 0) return $this->$prop;
        // if ($prop === 'tax') dd($this->$prop);
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

    public static function fromBuyable(Buyable $item)
    {
        return new self($item->getBuyable('id'), $item->getBuyable('name'), ['price' => $item->getBuyable('price'), 'comparePrice' => $item->getBuyable('comparePrice')], $item->getBuyableProps());
    }

    public function updateFromBuyable(Buyable $item)
    {
        $this->id       = $item->getBuyable('id') ?: $this->id;
        $this->name     = $item->getBuyable('name') ?: $this->name;
        $this->price    = $item->getBuyable('price') ?: $this->price;
        $this->comparePrice = $item->getBuyable('comparePrice') ?: $this->comparePrice;
        $this->taxable = $item->getBuyable('taxable') ?: $this->taxable;
        $this->taxRate = $item->getBuyable('taxRate') ?: $this->taxRate;
    }

    public static function fromArray(array $attributes)
    {
        $options = Arr::get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $options);
    }

    public function updateFromArray(array $attributes)
    {
        $this->id       = Arr::get($attributes, 'id', $this->id);
        $this->qty      = Arr::get($attributes, 'qty', $this->qty);
        $this->name     = Arr::get($attributes, 'name', $this->name);
        $this->price    = Arr::get($attributes, 'price', $this->price);
        $this->options  = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    public static function fromAttributes($id, $name, $price, array $options = [])
    {
        return new self($id, $name, $price, $options);
    }

    protected function generateRowId($id, array $options)
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    protected function throwError($message)
    {
        throw new InvalidArgumentException($message);
    }

    public function toArray()
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'comparePrice' => $this->comparePrice(false),
            'unitPrice' => $this->unitPrice(false),
            'price'    => $this->price(false),

            'taxable' => $this->taxable,
            'taxRate' => $this->taxRate(),
            'tax'      => $this->tax(false),

            'subtotal' => $this->subtotal(false),

            'coupons' => $this->coupons,
            'discount' => $this->discount,
            'fees' => $this->fees,

            'options'  => $this->options->toArray(),
            'associatedModel' => $this->associatedModel,
            'isSaved'      => $this->isSaved,
        ];
    }

    public function toJson($options = 0)
    {
        if (isset($this->associatedModel)) {

            return json_encode(array_merge($this->toArray(), ['model' => $this->model]), $options);
        }

        return json_encode($this->toArray(), $options);
    }

    protected function checkNumb($value)
    {
        $num = (float) str_replace([',', '$'], '', $value);
        return $num;
    }

    protected function comparePrice_multiplier()
    {
        $multiplier = config('cart.compare_price.default_multiplier', 1.3);
        if (is_numeric($multiplier)) return $multiplier;

        throw new ConfigError('Please Check ComparePrice Multiplier.');
    }

    public function format($value, $decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        $value = is_string($value) ? $this->$value : $value;

        if (is_null($decimals)) $decimals = config('cart.format.decimals', 2);

        if (is_null($decimalPoint)) $decimalPoint = config('cart.format.decimal_point', '.');

        if (is_null($thousandSeperator)) $thousandSeperator = config('cart.format.thousand_seperator', ',');

        return number_format($value, $decimals, $decimalPoint, $thousandSeperator);
    }

    public function __get($attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if ($attribute === 'priceTax') {
            return $this->priceTax(false, true);
        }

        if ($attribute === 'subtotal') {
            return $this->subtotal(false);
        }

        if ($attribute === 'total') {
            return $this->total(false);
        }

        if ($attribute === 'tax') {
            return $this->tax(false, true);
        }

        if ($attribute === 'taxTotal') {
            return $this->taxTotal(false);
        }

        if ($attribute === 'model' && isset($this->associatedModel)) {
            return with(new $this->associatedModel)->find($this->id);
        }

        return null;
    }

    public function name()
    {
        return $this->name;
    }

    public function qty()
    {
        return $this->qty;
    }

    public function comparePrice($format = true)
    {
        if ($format) return $this->format($this->comparePrice);
        return $this->comparePrice;
    }

    public function unitPrice($format = true)
    {
        return $this->price($format, false);
    }

    public function price($format = true, $includeDiscount = true)
    {
        $price = $this->processCoupons('price', $includeDiscount);

        if ($format) return $this->format($price);
        return $price;
    }

    public function tax($format = true, $includeDiscount = true)
    {
        if (!$this->taxable) return 0.00;

        $tax = ($this->price(false, $includeDiscount) * $this->taxRate('float'));

        if ($format) return $this->format($tax);
        return $tax;
    }

    public function taxRate($format = 'value')
    {
        switch ($format) {
            case 'string':
                return $this->taxRate . ' %';
            case 'float':
                return $this->taxRate / 100;
            case 'value':
            default:
                return $this->taxRate;
        }
    }

    public function subtotal($format = true, $includeDiscount = true)
    {
        $subtotal = ($this->qty * $this->price(false, $includeDiscount));

        if ($format) return $this->format($subtotal);
        return $subtotal;
    }

    public function priceTax($format = true, $includeDiscount = true)
    {
        if ($this->taxable) {
            $priceTax = ($this->price(false, $includeDiscount) + $this->tax(false, $includeDiscount));
        } else {
            $priceTax = ($this->price(false, $includeDiscount));
        }

        if ($format) return $this->format($priceTax);
        return $priceTax;
    }

    public function taxTotal($format = true, $includeDiscount = true)
    {
        $taxTotal = $this->tax(false, $includeDiscount) * $this->qty;

        if ($format) return $this->format($taxTotal);
        return $taxTotal;
    }

    public function total($format = true, $includeDiscount = true)
    {
        $total = ($this->qty * $this->priceTax(false, $includeDiscount));

        if ($format) return $this->format($total);
        return $total;
    }

    public function lineDiscount($format = true)
    {
        $discount = $this->total(false, false) - $this->total(false, true);

        if ($format) return $this->format($discount);
        return $discount;
    }
}
