<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class ProductTagsRepository extends Repository
{
    protected $tableName = 'product_tags';

    public function add($productId, $tagId)
    {
        return $this->insert([
            'product_id' => $productId,
            'tag_id' => $tagId,
        ]);
    }

    public function setProductTags($product, $tags)
    {
        $this->getTable()->where(['product_id' => $product->id])->delete();
        foreach ($tags as $id) {
            $this->add($product->id, $id);
        }
    }
}
