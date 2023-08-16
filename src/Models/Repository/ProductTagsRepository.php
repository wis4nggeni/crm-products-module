<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;

class ProductTagsRepository extends Repository
{
    protected $tableName = 'product_tags';

    final public function add(int $productId, int $tagId, int $sorting = null)
    {
        return $this->insert([
            'product_id' => $productId,
            'tag_id' => $tagId,
            'sorting' => $sorting,
        ]);
    }

    final public function setProductTags($product, $tags): void
    {
        $this->getTable()->where(['product_id' => $product->id])->delete();
        foreach ($tags as $id) {
            $this->add($product->id, $id);
        }
    }

    final public function setTagForProducts(ActiveRow $tag, array $productIds): void
    {
        $sorting = 1;
        foreach ($productIds as $productId) {
            $this->add($productId, $tag->id, $sorting);
            $sorting++;
        }
    }

    final public function removeTagFromProducts(ActiveRow $tag): int
    {
        return $this->getTable()
            ->where('tag_id', $tag->id)
            ->delete();
    }
}
