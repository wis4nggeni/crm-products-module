<?php

namespace Crm\ProductsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class ProductSaveEvent extends AbstractEvent implements ProductEventInterface
{
    public function __construct(private ActiveRow $product)
    {
    }

    public function getProduct(): ActiveRow
    {
        return $this->product;
    }
}
