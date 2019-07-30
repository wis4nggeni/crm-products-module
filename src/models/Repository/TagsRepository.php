<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class TagsRepository extends Repository
{
    protected $tableName = 'tags';

    public function all()
    {
        return $this->getTable()->order('-sorting DESC, code ASC');
    }

    public function counts()
    {
        return $this->getTable()
            ->where([
                ':product_tags.product.stock > ?' => 0,
            ])
            ->group(':product_tags.tag_id')
            ->select(':product_tags.tag_id AS id, COUNT(*) AS val');
    }

    public function add($code, $icon, $visible = false)
    {
        return $this->insert([
            'code' => $code,
            'icon' => $icon,
            'visible' => $visible,
        ]);
    }

    public function updateSorting($newSorting, $oldSorting = null)
    {
        if ($newSorting == $oldSorting) {
            return;
        }

        if ($oldSorting !== null) {
            $this->getTable()->where('sorting > ?', $oldSorting)->update(['sorting-=' => 1]);
        }

        $this->getTable()->where('sorting >= ?', $newSorting)->update(['sorting+=' => 1]);
    }
}
