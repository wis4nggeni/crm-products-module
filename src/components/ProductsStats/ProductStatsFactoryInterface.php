<?php

namespace Crm\ProductsModule\Components;

use Crm\SubscriptionsModule\Components\ProductStats;

interface ProductStatsFactoryInterface
{
    /** @return ProductStats */
    public function create();
}
