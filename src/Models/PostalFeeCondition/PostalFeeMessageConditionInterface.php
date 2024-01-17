<?php

namespace Crm\ProductsModule\Models\PostalFeeCondition;

interface PostalFeeMessageConditionInterface
{
    public function getReachedMessage(array $products, string $value): string;

    public function getNotReachedMessage(array $products, string $value): string;
}
