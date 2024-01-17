<?php

namespace Crm\ProductsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface SortShopProductsFormValidationDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params);
}
