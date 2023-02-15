<?php

namespace Crm\ProductsModule\Events;

use Crm\ApplicationModule\ActiveRow;
use League\Event\AbstractEvent;

class OrderStatusChangeEvent extends AbstractEvent implements OrderEventInterface
{
    public function __construct(private ActiveRow $order)
    {
    }

    public function getOrder(): ActiveRow
    {
        return $this->order;
    }
}
