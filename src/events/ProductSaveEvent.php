<?php

namespace Crm\ProductsModule\Events;

use League\Event\AbstractEvent;

class ProductSaveEvent extends AbstractEvent
{
    private $productId;

    public function __construct($productId)
    {
        $this->productId = $productId;
    }

    public function getProductId()
    {
        return $this->productId;
    }
}
