<?php

namespace Crm\ProductsModule\Events;

use League\Event\AbstractEvent;

class NewOrderEvent extends AbstractEvent
{
    private $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function getOrder()
    {
        return $this->order;
    }
}
