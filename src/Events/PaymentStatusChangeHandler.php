<?php

namespace Crm\ProductsModule\Events;

use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\Models\Manager\ProductManager;
use Crm\ProductsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\ProductsModule\Repositories\OrdersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

class PaymentStatusChangeHandler extends AbstractListener
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
        if (!($event instanceof PaymentChangeStatusEvent)) {
            throw new \Exception('Invalid type of event received, PaymentChangeStatusEvent expected: ' . get_class($event));
        }

        /** @var ActiveRow $payment */
        $payment = $event->getPayment();
        $order = $payment->related('orders')->fetch();

        if (!$order) {
            // this is not order payment
            return;
        }

        switch ($payment->status) {
            case PaymentsRepository::STATUS_PAID:
            case PaymentsRepository::STATUS_PREPAID:
                if (in_array($order->status, [OrdersRepository::STATUS_NEW, OrdersRepository::STATUS_PAYMENT_FAILED], true)) {
                    $this->ordersRepository->update($order, ['status' => OrdersRepository::STATUS_PAID]);
                }
                break;

            case PaymentsRepository::STATUS_FAIL:
            case PaymentsRepository::STATUS_TIMEOUT:
                $this->ordersRepository->update($order, ['status' => OrdersRepository::STATUS_PAYMENT_FAILED]);
                break;

            case PaymentsRepository::STATUS_REFUND:
                $this->ordersRepository->update($order, ['status' => OrdersRepository::STATUS_PAYMENT_REFUNDED]);
                break;

            case PaymentsRepository::STATUS_IMPORTED:
                $this->ordersRepository->update($order, ['status' => OrdersRepository::STATUS_IMPORTED]);
                break;

            default:
                Debugger::log("Unknown payment status: {$payment->status}. Payment ID: {$payment->id} Order ID: {$order->id}", Debugger::EXCEPTION);
                break;
        }
    }
}
