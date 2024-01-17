<?php

namespace Crm\ProductsModule\Events;

use Crm\ProductsModule\Models\Manager\ProductManager;
use Crm\ProductsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\ProductsModule\Repositories\OrdersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class OrderStatusChangeEventHandler extends AbstractListener
{
    private $paymentItemHelper;

    private $productManager;

    public function __construct(
        PaymentItemHelper $paymentItemHelper,
        ProductManager $productManager
    ) {
        $this->paymentItemHelper = $paymentItemHelper;
        $this->productManager = $productManager;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof OrderEventInterface) {
            throw new \Exception("Invalid type of event received, 'OrderEventInterface' expected: " . get_class($event));
        }

        $order = $event->getOrder();
        $payment = $order->payment;

        if ($order->status === OrdersRepository::STATUS_PAID) {
            $this->paymentItemHelper->unBundleProducts($payment, function ($product, $itemCount) {
                $this->productManager->decreaseStock($product, $itemCount);
            });
        }
    }
}
