<?php

use Phinx\Migration\AbstractMigration;

class AddUniqueIndexesProductProperties extends AbstractMigration
{
    public function change()
    {
        $this->table('product_properties')
            ->addIndex(['product_id', 'product_template_property_id'], ['unique' => true])
            ->update();

        $this->table('product_template_properties')
            ->addIndex(['code', 'product_template_id'], ['unique' => true])
            ->update();
    }
}
