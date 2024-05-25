<?php

namespace Mrkatz\Shoppingcart;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Mrkatz\Shoppingcart\Contracts\Buyable;
use Mrkatz\Shoppingcart\Exceptions\InvalidRowIDException;
use Mrkatz\Shoppingcart\Exceptions\UnknownModelException;
use Mrkatz\Shoppingcart\Exceptions\UnknownPropertyException;

use function Pest\Laravel\instance;

class Cart
{
    protected $session;
    private $events;
    private $instance;

    public $coupons = [];
    protected $discount = 0.00;
    private $cartFees = [];
    protected $fee = 0.00;

    public function __construct()
    {
        $this->session = app()->make(SessionManager::class);
        $this->events = app('events');

        $this->instance(config('cart.instances.default', 'default'));
        $this->cartFees = [];
        $this->coupons = [];
    }

    public function instance($instance = null)
    {
        $instance = $instance ?: config('cart.instances.default', 'default');

        $this->instance = sprintf('%s.%s', 'cart', $instance);

        return $this;
    }

    public function currentInstance()
    {
        return str_replace('cart.', '', $this->instance);
    }

    public static function getInstances()
    {
        return array_keys(session('cart'));
    }

    public function content()
    {
        return $this->getContent();
    }

    protected function getContent()
    {
        if ($this->session->has($this->instance)) {
            $cart = $this->session->get($this->instance);
            $this->cartFees = $cart['fees'] ?? [];
            $this->coupons = $cart['coupons'] ?? [];
            $cartItems = $cart['cartItems'] ?? [];

            return new Collection($cartItems);
        }

        return new Collection;
    }

    protected function storeContent($content)
    {
        $cartData = [
            'fees' => $this->cartFees,
            'coupons' => $this->coupons,
            'cartItems' => $content
        ];

        $this->session->put($this->instance, $cartData);
    }


    public function add($id, $name = null, $qty = null, $price = null, array $options = [], $taxrate = null)
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        if ($id instanceof CartItem) {
            $cartItem = $id;
        } else {
            $cartItem = $this->createCartItem($id, $name, $qty, $price, $options, $taxrate);
        }

        $cartItem->cart = $this->instance;

        $content = $this->getContent();

        if ($content->has($cartItem->rowId)) {
            $cartItem->qty += $content->get($cartItem->rowId)->qty;
        }

        $content->put($cartItem->rowId, $cartItem);

        $this->events->dispatch('cart.added', $cartItem);

        $this->storeContent($content);

        return $cartItem;
    }

    private function createCartItem($id, $name, $qty, $price, array $options, $taxrate)
    {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($id);
            $cartItem->options = new CartItemOptions($qty);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        } else {
            $cartItem = CartItem::fromAttributes($id, $name, $price, $options);
            $cartItem->setQuantity($qty);
        }

        if (array_key_exists('taxRate', $options)) {
            //Don't Clear - Set in Options
        } else
        if (isset($taxrate) && is_numeric($taxrate)) {
            $cartItem->setTaxRate($taxrate);
        } else {
            $cartItem->setTaxRate(config('cart.tax'));
        }

        return $cartItem;
    }

    public function update($rowId, $qty)
    {
        $id = ($rowId instanceof CartItem) ? $rowId->rowId : $rowId;

        $cartItem = $this->get($id);

        if ($qty instanceof Buyable) {
            $cartItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $cartItem->updateFromArray($qty);
        } else {
            $cartItem->qty = $qty;
        }

        $content = $this->getContent();

        if ($id !== $cartItem->rowId) {
            $content->pull($id);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);
            return;
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.updated', $cartItem);

        $this->storeContent($content);

        return $cartItem;
    }

    public function get($rowId)
    {
        $content = $this->getContent();

        $id = ($rowId instanceof CartItem) ? $rowId->rowId : $rowId;

        if (!$content->has($id))
            throw new InvalidRowIDException("The cart does not contain rowId {$id}.");

        return $content->get($id);
    }

    public function remove($rowId)
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        $this->events->dispatch('cart.removed', $cartItem);

        $this->storeContent($content);
    }

    public function destroy()
    {
        $this->session->remove($this->instance);
    }

    public function search(Closure $search)
    {
        $content = $this->getContent();

        return $content->filter($search);
    }

    public function associate($rowId, $model)
    {
        if (is_string($model) && !class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->storeContent($content);
    }

    //Coupons
    public function addCoupon(CartCoupon $coupon)
    {
        $content = $this->getContent();

        if ($coupon->canApply($this)) {

            if (!config('cart.coupon.allow_multiple')) {
                $this->coupons = [];
            }

            $this->coupons[$coupon->code] = $coupon;

            foreach ($content as $cartItem) {
                $cartItem->addCoupon($coupon);
            }

            $this->updateDiscount();

            $this->storeContent($content);
            return $this;
        }
        $coupon->throwError('Invalid Coupon');
    }

    public function addCouponType($type = null, $options = [])
    {
        switch ($type) {
            case 'comparePrice':
                $coupon = new CartCoupon(config('cart.compare_price.discount_code', today()->format('M') . 'only'), null, 'comparePrice', $options);
                $this->addCoupon($coupon);
                break;
            case 'value':
                $value = isset($options['value']) && is_numeric($options['value']) ? $options['value'] : null;
                $code = isset($options['code']) ? $options['code'] : today()->format('M') . 'only' . $options['value'];

                $coupon = new CartCoupon($code, $value, 'value', array_merge(['validProducts' => [$this->rowId]], $options));
                $this->addCoupon($coupon);
                break;
            case 'percentage':
                $value = isset($options['value']) && is_numeric($options['value']) && $options['value'] < 1.0 ? $options['value'] : null;
                $code = isset($options['code']) ? $options['code'] : today()->format('M') . 'only' . ($options['value'] * 100) . '%';

                $coupon = new CartCoupon($code, $value, 'percentage', array_merge(['validProducts' => [$this->rowId]], $options));
                $this->addCoupon($coupon);
                break;
            default:
                return $this;
        }
    }

    protected function updateDiscount()
    {
        $this->discount = 0;

        foreach ($this->coupons as $coupon) {
            if ($coupon->type === 'value') {
                $this->discount += $coupon->value;
            }
        }
    }

    public function clearCoupons()
    {
        $content = $this->getContent();
        foreach ($content as $cartItem) {
            $cartItem->coupons = [];
        }
        $this->coupons = [];
        $this->discount = 0;

        $this->storeContent($content);
    }

    public function addFee(CartFee $fee)
    {
        $content = $this->getContent();
        if ($fee->type === 'value') return $this->add($fee, $fee->name, 1, ['price' => $fee->value, 'comparePrice' => ''], $fee->options->toArray());
        $this->cartFees[$fee->name] = $fee;
        $cartFees = $this->cartFees;
        $this->updateFees();

        $this->cartFees = $cartFees;
        $this->storeContent($content);
        return $this;
    }

    public function cartFees($format = false)
    {
        $this->updateFees();

        if ($format) return $this->format($this->fee);
        return $this->fee;
    }

    protected function updateFees()
    {
        $this->fee = 0.00;
        $cartfees = $this->cartFees;
        foreach ($cartfees as $fee) {
            if ($fee->type !== 'value') {
                $this->fee += $this->total(false, ['discount' => false, 'fees' => false]) * $fee->value;
            }
        };
    }

    //Database
    protected function getConnection()
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    private function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }

    protected function getTableName()
    {
        return config('cart.database.table.shoppingcart', 'shoppingcart');
    }

    public function databaseStoreBlock()
    {
        $cartData = ['content' => $this->getContent()];

        if (config('cart.database.store.fees', true)) $cartData['fees'] = $this->cartFees;
        if (config('cart.database.store.coupon', true)) $cartData['coupon'] = $this->coupons;
        return $cartData;
    }

    public function store($identifier, $cartData = null)
    {
        if (is_null($cartData)) $cartData = $this->databaseStoreBlock();

        $this->getConnection()
            ->table($this->getTableName())
            ->where('identifier', $identifier)
            ->where('instance', $this->currentInstance())
            ->delete();


        $this->getConnection()->table($this->getTableName())->insert([
            'identifier' => $identifier,
            'instance' => $this->currentInstance(),
            'content' => serialize($cartData),
            'created_at' => new \DateTime()
        ]);

        $this->events->dispatch('cart.stored');
    }

    public function restore($identifier)
    {
        if (!$this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('instance', $this->currentInstance())
            ->where('identifier', $identifier)->first();

        $storedContent = unserialize(data_get($stored, 'content'));

        $currentInstance = $this->currentInstance();

        $this->instance(data_get($stored, 'instance'));

        $content = $this->getContent();

        foreach ($storedContent['content'] as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->cartFees = $storedContent['fees'];
        $this->coupons = $storedContent['coupon'];

        $this->events->dispatch('cart.restored');

        $this->instance($currentInstance);

        $this->storeContent($content);
    }

    public function deleteStoredCart($identifier)
    {
        $this->getConnection()
            ->table($this->getTableName())
            ->where('identifier', $identifier)
            ->delete();
    }

    protected function storedCartWithIdentifierExists($identifier)
    {
        return $this->getConnection()->table($this->getTableName())->where('identifier', $identifier)->where('instance', $this->currentInstance())->exists();
    }

    private function isMulti($item)
    {
        if (!is_array($item)) return false;

        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    public function format($value, $decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        $value = is_string($value) ? $this->$value : $value;

        if (floatval($value) === 0.0) return config('cart.format.on_zero', 0);

        if (is_null($decimals)) $decimals = config('cart.format.decimals', 2);

        if (is_null($decimalPoint)) $decimalPoint = config('cart.format.decimal_point', '.');

        if (is_null($thousandSeperator)) $thousandSeperator = config('cart.format.thousand_seperator', ',');

        $prepend = config('cart.format.prepend', '');

        return $prepend . number_format($value, $decimals, $decimalPoint, $thousandSeperator);
    }

    public function __get($attribute)
    {
        if ($attribute === 'total') {
            return $this->total(false);
        }

        if ($attribute === 'tax') {
            return $this->tax(false);
        }

        if ($attribute === 'subtotal') {
            return $this->subtotal(false);
        }

        return null;
    }

    public function setTax($rowId, $taxRate)
    {
        $cartItem = $this->get($rowId);

        $cartItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->storeContent($content);

        return $this;
    }

    public function cartQty($rowId = null)
    {
        $content = $this->getContent();

        $qty = $content->reduce(function ($qty, CartItem $cartItem) use ($rowId) {
            if (is_null($rowId)) return $qty + $cartItem->qty;
            if ($cartItem->rowId === $rowId) return $qty + $cartItem->qty;
        }, 0);

        return $qty;
    }
    public function count()
    {
        $content = $this->getContent();

        return $content->sum('qty');
    }

    public function total($format = true, $options = ['discount' => true, 'fees' => true])
    {
        $content = $this->getContent();

        $total = $content->reduce(function ($total, CartItem $cartItem) {
            return $total + ($cartItem->qty * $cartItem->priceTax(false));
        }, 0);

        if ($options['discount']) $total -= $this->cartDiscount(false);
        if ($options['fees']) $total += $this->fee;

        if ($format) return $this->format($total);
        return $total;
    }

    public function tax($format = true)
    {
        $content = $this->getContent();

        $tax = $content->reduce(function ($tax, CartItem $cartItem) {
            return $tax + ($cartItem->qty * $cartItem->tax(false));
        }, 0);

        if ($format) return $this->format($tax);
        return $tax;
    }

    public function subtotal($format = true)
    {
        $content = $this->getContent();

        $subTotal = $content->reduce(function ($subTotal, CartItem $cartItem) {
            return $subTotal + ($cartItem->qty * $cartItem->price(false));
        }, 0);

        if ($format) return $this->format($subTotal);
        return $subTotal;
    }

    public function cartDiscount($format = true)
    {
        $this->updateDiscount();

        if ($format) return $this->format($this->discount);
        return $this->discount;
    }

    public function savings($format = true)
    {
        $content = $this->getContent();

        $savings = $content->reduce(function ($savings, CartItem $cartItem) {
            return $savings + $cartItem->lineDiscount(false);
        }, 0);

        $savings += $this->cartDiscount(false);

        if ($format) return $this->format($savings);
        return $savings;
    }

    public function comparePrice($format = true)
    {
        $content = $this->getContent();

        $compare = $content->reduce(function ($compare, CartItem $cartItem) {
            return $compare + ($cartItem->qty * $cartItem->comparePrice(false));
        }, 0);

        if ($format) return $this->format($compare);
        return $compare;
    }

    public function withInstance($instances, $value, $format = true, $options = ['discount' => true, 'fees' => true])
    {
        if (!in_array($value, ['total', 'count', 'tax', 'subtotal', 'cartDiscount', 'cartFees', 'savings', 'comparePrice'])) {
            throw new UnknownPropertyException($value . ' is not a valid property on Multi-instance method');
        }
        $sum = array_sum(array_map(
            fn ($instance) => $this->instance($instance)->$value(false, $value === 'total' ? $options : null),
            $instances
        ));

        return $format ? $this->format($sum) : $sum;
    }
}
