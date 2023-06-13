<?php

use Phinx\Migration\AbstractMigration;

class AddPostalFeesFields extends AbstractMigration
{
    public function up()
    {
        $this->table('country_postal_fees')
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->update();

        $this->query("UPDATE country_postal_fees SET created_at=NOW(), updated_at=NOW()");

        $this->table('country_postal_fees')
            ->changeColumn('created_at', 'datetime', ['null' => false])
            ->changeColumn('updated_at', 'datetime', ['null' => false])
            ->update();

        $this->table('postal_fees')
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->update();

        $this->query("UPDATE postal_fees SET created_at=NOW(), updated_at=NOW()");

        $this->table('postal_fees')
            ->changeColumn('created_at', 'datetime', ['null' => false])
            ->changeColumn('updated_at', 'datetime', ['null' => false])
            ->update();
    }

    public function down()
    {
        $this->table('country_postal_fees')
            ->removeColumn('active')
            ->removeColumn('created_at')
            ->removeColumn('updated_at')
            ->update();

        $this->table('postal_fees')
            ->removeColumn('created_at')
            ->removeColumn('updated_at')
            ->update();
    }
}
