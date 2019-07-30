<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class ProductTemplatesRepository extends Repository
{
    protected $tableName = 'product_templates';

    public function all()
    {
        return $this->getTable();
    }

    public function add($name)
    {
        return $this->insert([
            'name' => $name
        ]);
    }
}
