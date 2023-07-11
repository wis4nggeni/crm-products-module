<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTagsOptions extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tags')
            ->addColumn('html_heading', 'text', ['after' => 'name'])
            ->addColumn('user_assignable', 'boolean', ['after' => 'visible'])
            ->addColumn('frontend_visible', 'boolean', ['after' => 'visible'])
            ->update();

        $this->execute("UPDATE tags SET html_heading = name, user_assignable = 1, frontend_visible = 0;");
    }
}
