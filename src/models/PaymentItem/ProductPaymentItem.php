<?php

namespace Crm\ProductsModule\PaymentItem;

use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\PaymentItem\PaymentItemTrait;
use Nette\Database\Table\IRow;

class ProductPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    const TYPE = 'product';

    private $product;

    public function __construct(IRow $product, int $count)
    {
        $this->product = $product;
        $this->count = $count;
        $this->name = $product->name;
        $this->price = $product->price;
        $this->vat = $product->vat;
    }

    public function forcePrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function forceVat(int $vat): self
    {
        $this->vat = $vat;
        return $this;
    }

    public function forceName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function data(): array
    {
        return [
            'product_id' => $this->product->id,
        ];
    }

    public function meta(): array
    {
        return [];
    }
}
