<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeProductTagsSortingNonNullable extends AbstractMigration
{
    public function change(): void
    {
        $this->query("UPDATE product_tags SET sorting = 100 WHERE sorting IS NULL;");

        $this->table('product_tags')
            ->changeColumn('sorting', 'integer', ['null' => false, 'default' => 100])
            ->update();
    }
}
