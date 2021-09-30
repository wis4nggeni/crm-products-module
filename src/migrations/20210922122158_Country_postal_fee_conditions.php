<?php

use Phinx\Migration\AbstractMigration;

class CountryPostalFeeConditions extends AbstractMigration
{
    public function change()
    {
        $this->table('country_postal_fee_conditions')
            ->addColumn('country_postal_fee_id', 'integer', ['null' => false])
            ->addColumn('code', 'string', ['null' => false])
            ->addColumn('value', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addForeignKey('country_postal_fee_id', 'country_postal_fees', 'id')
            ->create();
    }
}
