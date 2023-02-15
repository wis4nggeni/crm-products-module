<?php

namespace Crm\ProductsModule\Events;

use Nette\Database\Table\ActiveRow;

interface OrderEventInterface
{
    public function getOrder(): ActiveRow;
}