<?php

use Phinx\Migration\AbstractMigration;

class AddDeletedAtColumnToProductsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('products')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->update();
    }
}
