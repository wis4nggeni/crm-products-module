<?php

use Phinx\Migration\AbstractMigration;

class ModifyProductPropertiesValueNotNull extends AbstractMigration
{
    public function up()
    {
        $this->table('product_properties')
            ->changeColumn('value', 'text', ['null' => true])
            ->save();
    }

    public function down()
    {
        $this->table('product_properties')
            ->changeColumn('value', 'text', ['null' => false])
            ->save();
    }
}
