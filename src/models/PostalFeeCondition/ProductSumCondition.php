<?php

namespace Crm\ProductsModule\PostalFeeCondition;

use Crm\ProductsModule\Repository\ProductsRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;

class ProductSumCondition implements PostalFeeConditionInterface, PostalFeeNumericConditionInterface
{
    public const CONDITION_CODE = 'products_sum';

    private $translator;

    private $productsRepository;

    public function __construct(
        Translator $translator,
        ProductsRepository $productsRepository
    ) {
        $this->translator = $translator;
        $this->productsRepository = $productsRepository;
    }

    public function getLabel(): string
    {
        return $this->translator->translate('products.admin.country_postal_fees.conditions.products_sum.label');
    }

    public function isReached(array $products, string $value): bool
    {
        return $this->sumProductsFromCart($products) >= (float)$value;
    }

    public function getActualValue(array $products): float
    {
        return $this->sumProductsFromCart($products);
    }

    public function getValidationRules(): array
    {
        return [
            [Form::FLOAT, $this->translator->translate('products.admin.country_postal_fees.conditions.products_sum.validation_integer')],
            [Form::MIN, $this->translator->translate('products.admin.country_postal_fees.conditions.products_sum.validation_min'), 0],
        ];
    }

    private function sumProductsFromCart(array $products): float
    {
        $sum = 0;
        foreach ($products as $productId => $count) {
            $productRow = $this->productsRepository->find($productId);
            $sum += $productRow->price * $count;
        }

        return $sum;
    }
}
