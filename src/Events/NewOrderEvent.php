<?php

namespace Crm\ProductsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewOrderEvent extends AbstractEvent implements OrderEventInterface
{
    public function __construct(private ActiveRow $order)
    {
    }

    public function getOrder(): ActiveRow
    {
        return $this->order;
    }
}
