<?php

namespace Crm\ProductsModule\PostalFeeCondition;

use Nette\ComponentModel\IComponent;

interface PostalFeeConditionInterface
{
    public function isReached(array $products, string $value, int $userId = null): bool;

    public function getLabel(): string;

    public function getInputControl(): IComponent;
}
