<?php

namespace Crm\ProductsModule\Models;

trait CartTrait
{
    private $cartSession;

    private $cartProducts;

    private $cartProductSum;

    private function buildCartSession()
    {
        $this->cartSession = $this->getSession('cart');

        if (!isset($this->cartSession->products)) {
            $this->cartSession->products = [];
        }
        if (!isset($this->cartSession->freeProducts)) {
            $this->cartSession->freeProducts = [];
        }

        // check and throw away any deleted products
        $cartProductIds = array_merge(
            array_keys($this->cartSession->products),
            array_keys($this->cartSession->freeProducts),
        );
        $availableProductIds = array_keys($this->productsRepository->findByIds($cartProductIds));
        $unavailableProductIds = array_diff($cartProductIds, $availableProductIds);

        foreach ($unavailableProductIds as $productId) {
            unset($this->cartSession->products[$productId]);
            unset($this->cartSession->freeProducts[$productId]);
        }

        $this->cartProducts = $this->cartSession->products;
        $this->cartProductSum = array_sum($this->cartProducts);
    }
}
