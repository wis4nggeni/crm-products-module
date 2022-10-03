<?php

namespace Crm\ProductsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface SortShopProductsFormValidationDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params);
}
