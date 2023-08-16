<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSortingIntoProductTags extends AbstractMigration
{
    public function change(): void
    {
        $this->table('product_tags')
            ->addColumn('sorting', 'integer', ['default' => 100])
            ->update();
    }
}
