<?php

namespace Crm\ProductsModule\Models\PostalFeeCondition;

interface PostalFeeNumericConditionInterface
{
    public function getActualValue(array $products): float;
}
