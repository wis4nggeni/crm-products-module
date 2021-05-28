<?php

namespace Crm\ProductsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\IRow;

interface ProductTemplatePropertiesDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function beforeUpdate(IRow $product, IRow $templateProperty);

    public function afterSave(IRow $product, IRow $templateProperty);
}
