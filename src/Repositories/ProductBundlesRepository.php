<?php

namespace Crm\ProductsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;

class ProductBundlesRepository extends Repository
{
    protected $tableName = 'product_bundles';

    final public function add($productId, $itemId)
    {
        return $this->insert([
            'bundle_id' => $productId,
            'item_id' => $itemId,
        ]);
    }

    final public function setBundleItems($product, $bundleItems)
    {
        $this->getTable()->where(['bundle_id' => $product->id])->delete();
        foreach ($bundleItems as $id) {
            $this->add($product->id, $id);
        }
    }
}
