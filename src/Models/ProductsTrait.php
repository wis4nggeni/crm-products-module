<?php

namespace Crm\ProductsModule\Models;

use Crm\ProductsModule\Repository\DistributionCentersRepository;

trait ProductsTrait
{
    public function hasDelivery(array $products): bool
    {
        foreach ($products as $product) {
            if ($product->bundle) {
                foreach ($product->related('product_bundles') as $productBundle) {
                    if ($productBundle->item->has_delivery) {
                        return true;
                    }
                }
            } elseif ($product->has_delivery) {
                return true;
            }
        }

        return false;
    }

    public function hasLicense(array $products): bool
    {
        foreach ($products as $product) {
            if ($product->bundle) {
                foreach ($product->related('product_bundles') as $productBundle) {
                    if ($productBundle->item->distribution_center === DistributionCentersRepository::DISTRIBUTION_CENTER_DIBUK) {
                        return true;
                    }
                }
            } elseif ($product->distribution_center === DistributionCentersRepository::DISTRIBUTION_CENTER_DIBUK) {
                return true;
            }
        }

        return false;
    }
}
