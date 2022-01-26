<?php

namespace Crm\ProductsModule\PostalFeeCondition;

interface PostalFeeNumericConditionInterface
{
    public function getActualValue(array $products): float;
}
