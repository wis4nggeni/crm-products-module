<?php

namespace Crm\ProductsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface ProductsFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;
}
