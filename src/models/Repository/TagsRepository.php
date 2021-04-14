<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class TagsRepository extends Repository
{
    protected $tableName = 'tags';

    final public function all()
    {
        return $this->getTable()->order('sorting IS NOT NULL, sorting ASC, code ASC');
    }

    final public function counts()
    {
        return $this->getTable()
            ->where([
                ':product_tags.product.stock > ?' => 0
            ])
            ->group(':product_tags.tag_id')
            ->select(':product_tags.tag_id AS id, COUNT(*) AS val');
    }

    final public function add($code, $name, $icon, $visible = false)
    {
        return $this->insert([
            'code' => $code,
            'name' => $name,
            'icon' => $icon,
            'visible' => $visible,
        ]);
    }

    final public function updateSorting($newSorting, $oldSorting = null)
    {
        if ($newSorting == $oldSorting) {
            return;
        }

        if ($oldSorting !== null) {
            $this->getTable()->where('sorting > ?', $oldSorting)->update(['sorting-=' => 1]);
        }

        $this->getTable()->where('sorting >= ?', $newSorting)->update(['sorting+=' => 1]);
    }

    final public function isTagUsed(int $id): bool
    {
        return $this->getTable()->where([':product_tags.tag_id' => $id])->count() > 0;
    }
}
