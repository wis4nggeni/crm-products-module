<?php

namespace Crm\ProductsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Selection;
use Crm\ProductsModule\Repository\OrdersRepository;

class OrderScenarioConditionalModel implements ScenarioConditionModelInterface
{
    private $ordersRepository;

    public function __construct(OrdersRepository $ordersRepository)
    {
        $this->ordersRepository = $ordersRepository;
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->order_id)) {
            throw new \Exception("Order scenario conditional model requires 'order_id' job param.");
        }

        return $this->ordersRepository->getTable()->where(['orders.id' => $scenarioJobParameters->order_id]);
    }
}
