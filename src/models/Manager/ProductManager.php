<?php

namespace Crm\ProductsModule\Manager;

use Nette\Database\Table\ActiveRow;
use Crm\ProductsModule\Repository\ProductsRepository;

class ProductManager
{
    private $productsRepository;

    public function __construct(ProductsRepository $productsRepository)
    {
        $this->productsRepository = $productsRepository;
    }

    public function decreaseStock(ActiveRow $product, int $itemCount): void
    {
        $this->productsRepository->decreaseStock($product, $itemCount);

        foreach ($product->related('product_bundles', 'item_id') as $item) {
            $mainProduct = $item->bundle;
            if ($mainProduct->stock > $product->stock) {
                $this->productsRepository->update($mainProduct, ['stock' => $product->stock]);
            }
        }
    }
}
