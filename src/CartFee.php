<?php

namespace Mrkatz\Shoppingcart;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Mrkatz\Shoppingcart\Exceptions\CouponException;

class CartFee extends Collection
{
    public $name;
    public $type;
    public $value;
    public $taxable;


    public $options;

    public function __construct($name, $type = 'percentage', $value = 0.02, $options = [])
    {
        if (!in_array($type, ['percentage', 'value'])) $this->throwError('Invalid Fee Type. Type should be "percentage" or "value"');
        if ($type === 'percentage' && $value > 1) $this->throwError('Invalid value for a percentage fee. The value must be between 0 and 1.');

        $this->name = $name;
        $this->type = $type;
        $this->value = $value;

        $this->options = collect($options);
        // $this->setOptions($options);
    }

    /**
     * Sets all the options for the fee.
     *
     * @param $options
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    protected function throwError($message)
    {
        throw new CouponException($message);
    }

    public function __get($option)
    {
        return Arr::get($this->options, $option);
    }

    protected function getConnection()
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    protected function getTableName()
    {
        return config('cart.database.table.fees', 'fees');
    }

    private function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }
}
