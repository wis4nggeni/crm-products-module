<?php

namespace Crm\ProductsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;

class CartItemAddedEvent extends AbstractEvent
{
    private $product;

    public function __construct(IRow $product)
    {
        $this->product = $product;
    }

    public function getProduct(): IRow
    {
        return $this->product;
    }
}
