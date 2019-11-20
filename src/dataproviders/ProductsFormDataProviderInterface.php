<?php

namespace Crm\ProductsModule\DataProvider;

use Nette\Application\UI\Form;
use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface ProductsFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;
}
