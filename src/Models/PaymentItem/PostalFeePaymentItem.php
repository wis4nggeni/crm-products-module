<?php

namespace Crm\ProductsModule\Models\PaymentItem;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemTrait;
use Nette\Database\Table\ActiveRow;

class PostalFeePaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    public const TYPE = 'postal_fee';

    private ActiveRow $postalFee;

    public static function fromPaymentItem(ActiveRow $paymentItem): self
    {
        $item = new self($paymentItem->postal_fee, $paymentItem->vat, $paymentItem->count);
        $item->forcePrice($paymentItem->amount)
            ->forceName($paymentItem->name);
        return $item;
    }

    public function __construct(ActiveRow $postalFee, int $vat, int $count = 1)
    {
        $this->postalFee = $postalFee;
        $this->vat = $vat;
        $this->count = $count;
        $this->name = $postalFee->title;
        $this->price = $postalFee->amount;
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

    public function forceVat(int $vat): static
    {
        $this->vat = $vat;
        return $this;
    }

    public function data(): array
    {
        return [
            'postal_fee_id' => $this->postalFee->id,
        ];
    }

    public function meta(): array
    {
        return [];
    }
}
