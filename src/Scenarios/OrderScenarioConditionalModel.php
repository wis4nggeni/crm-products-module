<?php

namespace Crm\ProductsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\ProductsModule\Repositories\OrdersRepository;

class OrderScenarioConditionalModel implements ScenarioConditionModelInterface
{
    public function __construct(
        private readonly OrdersRepository $ordersRepository,
    ) {
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->order_id)) {
            throw new \Exception("Order scenario conditional model requires 'order_id' job param.");
        }

        return $this->ordersRepository->getTable()->where(['orders.id' => $scenarioJobParameters->order_id]);
    }
}
