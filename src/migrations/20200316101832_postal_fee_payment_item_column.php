<?php

use Phinx\Migration\AbstractMigration;

class PostalFeePaymentItemColumn extends AbstractMigration
{
    public function change()
    {
        if (!$this->table('payment_items')->hasColumn('postal_fee_id')) {
            $this->table('payment_items')
                ->addColumn('postal_fee_id', 'integer', ['null' => true, 'after' => 'product_id'])
                ->addForeignKey('postal_fee_id', 'postal_fees')
                ->save();
        }
    }
}
