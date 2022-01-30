<?php

namespace Crm\ProductsModule\Events;

use League\Event\AbstractEvent;

class OrderStatusChangeEvent extends AbstractEvent
{
    private $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }
}
