<?php

namespace Crm\ProductsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class CartItemAddedEvent extends AbstractEvent implements ProductEventInterface
{
    private $product;

    public function __construct(ActiveRow $product)
    {
        $this->product = $product;
    }

    public function getProduct(): ActiveRow
    {
        return $this->product;
    }
}
