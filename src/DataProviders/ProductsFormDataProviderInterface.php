<?php

namespace Crm\ProductsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface ProductsFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;
}
