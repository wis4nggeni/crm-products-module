<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface CheckoutFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function formSucceeded($form, $values, array $params);

    public function addAdditionalColumns($form, $values, &$additionalColumns);
}
