<?php

namespace Crm\ProductsModule\Events;

use Crm\ApplicationModule\ActiveRow;
use League\Event\AbstractEvent;

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
