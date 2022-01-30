<?php

namespace Crm\ProductsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

interface ProductTemplatePropertiesDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function beforeUpdate(ActiveRow $product, ActiveRow $templateProperty);

    public function afterSave(ActiveRow $product, ActiveRow $templateProperty);
}
