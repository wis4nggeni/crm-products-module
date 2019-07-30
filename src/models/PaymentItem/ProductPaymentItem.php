<?php

namespace Crm\ProductsModule\PaymentItem;

use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Nette\Database\Table\IRow;

class ProductPaymentItem implements PaymentItemInterface
{
    const TYPE = 'product';

    private $product;

    private $count;

    private $price = false;

    private $vat = false;

    public function __construct(IRow $product, int $count)
    {
        $this->product = $product;
        $this->count = $count;
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

    public function type(): string
    {
        return self::TYPE;
    }

    public function name(): string
    {
        return $this->product->name;
    }

    public function unitPrice(): float
    {
        return $this->price === false ? $this->product->price : $this->price;
    }

    public function totalPrice(): float
    {
        return $this->unitPrice() * $this->count();
    }

    public function vat(): int
    {
        return $this->vat === false ? $this->product->vat : $this->vat;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function data(): array
    {
        return [
            'product_id' => $this->product->id,
        ];
    }
}
