<?php

namespace Crm\ProductsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\ProductsModule\Repository\OrdersRepository;
use Kdyby\Translation\Translator;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class OrderStatusCriteria implements ScenariosCriteriaInterface
{
    const KEY = 'order_status';

    private $ordersRepository;

    private $translator;

    public function __construct(
        OrdersRepository $ordersRepository,
        Translator $translator
    ) {
        $this->ordersRepository = $ordersRepository;
        $this->translator = $translator;
    }

    public function params(): array
    {
        $statuses = $this->ordersRepository->getStatusPairs();

        return [
            new StringLabeledArrayParam(self::KEY, $this->label(), $statuses),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('status IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('products.admin.scenarios.order_status.label');
    }
}
