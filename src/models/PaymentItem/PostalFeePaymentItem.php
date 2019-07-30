<?php

namespace Crm\ProductsModule\PaymentItem;

use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Nette\Database\Table\IRow;

class PostalFeePaymentItem implements PaymentItemInterface
{
    const TYPE = 'postal_fee';

    private $postalFee;

    private $price = false;

    private $vat = false;

    private $name = false;

    public function __construct(IRow $postalFee, int $vat)
    {
        $this->postalFee = $postalFee;
        $this->vat = $vat;
    }

    public function forcePrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function forceName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function name(): string
    {
        return $this->name === false ? $this->postalFee->title : $this->name;
    }

    public function unitPrice(): float
    {
        return $this->price === false ? $this->postalFee->amount : $this->price;
    }

    public function totalPrice(): float
    {
        return $this->unitPrice();
    }

    public function vat(): int
    {
        return $this->vat;
    }

    public function count(): int
    {
        return 1;
    }

    public function data(): array
    {
        return [
            'postal_fee_id' => $this->postalFee->id,
        ];
    }
}
