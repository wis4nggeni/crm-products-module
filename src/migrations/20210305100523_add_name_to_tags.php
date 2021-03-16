<?php

use Nette\Utils\Strings;
use Phinx\Migration\AbstractMigration;

class AddNameToTags extends AbstractMigration
{
    public function up()
    {
        $this->table('tags')
            ->addColumn('name', 'string', ['after' => 'code'])
            ->update();

        $rows = $this->fetchAll('SELECT * FROM tags');
        $processedTags = [];
        foreach ($rows as $row) {
            $webCode = Strings::webalize($row['code']);

            if (array_key_exists($webCode, $processedTags)) {
                $this->getQueryBuilder()->update('product_tags')
                    ->set('tag_id', $processedTags[$webCode])
                    ->where(['tag_id' => $row['id']])
                    ->execute();

                $this->getQueryBuilder()->delete('tags')->where(['id' => $row['id']])->execute();
            } else {
                $processedTags[$webCode] = $row['id'];
                $this->getQueryBuilder()->update('tags')
                    ->set('name', $row['code'])
                    ->set('code', $webCode)
                    ->where(['id' => $row['id']])
                    ->execute();
            }
        }

        $this->table('tags')
            ->addIndex('code', ['unique' => true])
            ->update();
    }

    public function down()
    {
        $this->table('tags')
            ->removeIndex('code')
            ->update();

        $sql = <<<SQL
UPDATE tags SET code = name;
SQL;
        $this->execute($sql);

        $this->table('tags')
            ->removeColumn('name')
            ->update();
    }
}
