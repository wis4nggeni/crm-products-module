<?php

namespace Crm\ProductsModule\PaymentItem;

use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;

class PaymentItemHelper
{
    private $paymentItemsRepository;

    public function __construct(PaymentItemsRepository $paymentItemsRepository)
    {
        $this->paymentItemsRepository = $paymentItemsRepository;
    }

    public function hasUniqueProduct(IRow $product, int $userId): bool
    {
        return $this->paymentItemsRepository->getTable()->where([
            'payment.user_id' => $userId,
            'payment.status' => PaymentsRepository::STATUS_PAID,
            'type' => ProductPaymentItem::TYPE,
            'product_id' => $product->id
        ])->count('*') > 0;
    }

    public function unBundleProducts(ActiveRow $payment, callable $callback)
    {
        foreach ($payment->related('payment_items')->where('type = ?', ProductPaymentItem::TYPE) as $paymentItem) {
            $product = $paymentItem->product;
            if ($product->bundle) {
                foreach ($product->related('product_bundles') as $productBundle) {
                    $callback($productBundle->item, $paymentItem->count, $paymentItem->amount);
                }
            } else {
                $callback($product, $paymentItem->count, $paymentItem->amount);
            }
        }
    }
}
