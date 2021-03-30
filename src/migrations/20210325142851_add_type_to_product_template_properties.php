<?php

use Phinx\Migration\AbstractMigration;

class AddTypeToProductTemplateProperties extends AbstractMigration
{

    public function change()
    {
        $this->table('product_template_properties')
            ->addColumn('type', 'string', ['after' => 'code', 'default' => 'text'])
            ->update();

        $this->table('product_template_properties')
            ->changeColumn('type', 'string', ['default' => null])
            ->update();
    }
}
