<?php

namespace Crm\ProductsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface ProductManagerDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): ActiveRow;
}
