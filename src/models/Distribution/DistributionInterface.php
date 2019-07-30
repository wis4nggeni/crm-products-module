<?php

namespace Crm\ProductsModule\Distribution;

use Nette\Database\Context;

interface DistributionInterface
{
    public function distribution(Context $database, int $productId, array $levels): array;

    public function distributionList(Context $database, int $productId, float $fromLevel, float $toLevel = null): array;
}
