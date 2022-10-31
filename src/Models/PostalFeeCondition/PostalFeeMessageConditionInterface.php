<?php

namespace Crm\ProductsModule\PostalFeeCondition;

interface PostalFeeMessageConditionInterface
{
    public function getReachedMessage(array $products, string $value): string;

    public function getNotReachedMessage(array $products, string $value): string;
}
