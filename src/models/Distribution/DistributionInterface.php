<?php

namespace Crm\ProductsModule\Distribution;

interface DistributionInterface
{
    public function distribution(int $productId, array $levels): array;

    public function distributionList(int $productId, float $fromLevel, float $toLevel = null): array;
}
