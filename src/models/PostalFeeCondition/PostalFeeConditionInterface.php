<?php

namespace Crm\ProductsModule\PostalFeeCondition;

interface PostalFeeConditionInterface
{
    public function isReached(array $products, string $value): bool;

    public function getLabel(): string;

    /** @return array - Array of Nette like validation Rules definition */
    public function getValidationRules(): array;
}
