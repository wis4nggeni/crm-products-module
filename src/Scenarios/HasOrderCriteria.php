<?php

namespace Crm\ProductsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class HasOrderCriteria implements ScenariosCriteriaInterface
{
    public function params(): array
    {
        return [
            new BooleanParam('has_order', $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
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
