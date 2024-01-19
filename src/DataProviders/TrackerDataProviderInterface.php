<?php

namespace Crm\ProductsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;

interface TrackerDataProviderInterface extends DataProviderInterface
{
    public function provide(?array $params = []): array;
}
