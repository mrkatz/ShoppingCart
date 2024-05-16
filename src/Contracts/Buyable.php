<?php

namespace Mrkatz\Shoppingcart\Contracts;

interface Buyable
{
    /**
     * Get Buyable settings/options.
     *
     * @return array|mixed
     */
    public function getBuyable($property = 'All');

    /**
     * Get Buyable properties.
     *
     * @return array|mixed
     */
    public function getBuyableProps();
}
