<?php

use Phinx\Migration\AbstractMigration;

class AddAvailableDateToProductsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('products')
            ->addColumn('available_at', 'datetime', ['null' => true])
            ->update();
    }
}
