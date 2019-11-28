<?php

use Phinx\Migration\AbstractMigration;

class RenameOrdersNote extends AbstractMigration
{
    public function change()
    {
        $this->table('orders')
            ->renameColumn('coupon_note', 'note')
            ->update();
    }
}
