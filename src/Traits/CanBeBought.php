<?php

namespace Mrkatz\Shoppingcart\Traits;

use Mrkatz\Shoppingcart\Exceptions\BuyableException;

trait CanBeBought
{
    public function getBuyable($property = 'All')
    {
        if ($property === 'All') return $this->getBuyableProps();

        $key = config('cart.buyable.model.' . self::class . '.' . $property);
        if (is_null($key)) throw new BuyableException("Buyable $property not Set");

        return $this->resolveBuyableValue($key);
    }

    public function getBuyableProps()
    {
        $array = [];
        foreach (config('cart.buyable.model.' . self::class) as $key => $value) {
            $array[$key] = $this->resolveBuyableValue($value);
        }
        return $array;
    }

    protected function resolveBuyableValue($string)
    {
        if (strpos($string, '(') !== false && strpos($string, ')') !== false) {

            preg_match('/^([a-zA-Z0-9_]+)\((.*?)\)$/', $string, $matches);
            $methodName  = $matches[1];
            $arguments = explode(',', $matches[2]);

            $trimmedArguments = array_map('trim', $arguments);

            if (method_exists($this, $methodName)) {
                return call_user_func_array([$this, $methodName], $trimmedArguments);
            }
            return $this->$methodName;
        }

        if (strpos($string, '{') === 0 && strpos($string, '}') === strlen($string) - 1) {
            return substr($string, 1, -1);
        }

        return $this->$string;
    }
}
