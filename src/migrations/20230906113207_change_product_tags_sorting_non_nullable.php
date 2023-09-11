<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeProductTagsSortingNonNullable extends AbstractMigration
{
    public function up(): void
    {
        $this->query("UPDATE product_tags SET sorting = 100 WHERE sorting IS NULL;");

        $this->table('product_tags')
            ->changeColumn('sorting', 'integer', ['null' => false, 'default' => 100])
            ->update();
    }

    public function down(): void
    {
        $this->table('product_tags')
            ->changeColumn('sorting', 'integer', ['null' => true, 'default' => 100]) // revert not NULLABLE
            ->update();
    }
}
