<?php

use Phinx\Migration\AbstractMigration;

class FixNulldatesInAvailabileAt extends AbstractMigration
{
    public function up()
    {
        $this->execute(<<<SQL
            UPDATE `products`
            SET `available_at` = NULL
            -- select dates created from empty string / 0 / other issues
            -- found `0000-00-00 00:00:00` and `0001-01-30 00:00:00`
            -- no book should be available before Gutenberg Bible ¯\_(ツ)_/¯
            WHERE `available_at` < "1450-01-01";
SQL
        );
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
