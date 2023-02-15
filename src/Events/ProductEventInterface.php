<?php

namespace Crm\ProductsModule\Events;

use Nette\Database\Table\ActiveRow;

interface ProductEventInterface
{
    public function getProduct(): ActiveRow;
}