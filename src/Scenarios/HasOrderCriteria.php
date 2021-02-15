<?php

namespace Crm\ProductsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class HasOrderCriteria implements ScenariosCriteriaInterface
{
    public function params(): array
    {
        return [
            new BooleanParam('has_order', $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues['has_order'];

        if ($values->selection) {
            $selection->where(':orders.id IS NOT NULL');
        } else {
            $selection->where(':orders.id IS NULL');
        }

        return true;
    }

    public function label(): string
    {
        return 'Has shop order';
    }
}
