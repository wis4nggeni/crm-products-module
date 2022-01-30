<?php

namespace Crm\ProductsModule\Events;

use Crm\ProductsModule\Manager\ProductManager;
use Crm\ProductsModule\PaymentItem\PaymentItemHelper;
use Crm\ProductsModule\Repository\OrdersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class OrderStatusChangeEventHandler extends AbstractListener
{
    private $ordersRepository;

    private $paymentItemHelper;

    private $productManager;

    public function __construct(
        OrdersRepository $ordersRepository,
        PaymentItemHelper $paymentItemHelper,
        ProductManager $productManager
    ) {
        $this->ordersRepository = $ordersRepository;
        $this->paymentItemHelper = $paymentItemHelper;
        $this->productManager = $productManager;
    }

    public function handle(EventInterface $event)
    {
        $order = $this->ordersRepository->find($event->getOrderId());
        $payment = $order->payment;

        if ($order->status === OrdersRepository::STATUS_PAID) {
            $this->paymentItemHelper->unBundleProducts($payment, function ($product, $itemCount) {
                $this->productManager->decreaseStock($product, $itemCount);
            });
        }
    }
}
