<?php

namespace Crm\ProductsModule\Scenarios;

use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Repository\JobsRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class OrderStatusChangeHandler implements HandlerInterface
{
    private $dispatcher;

    private $ordersRepository;

    public function __construct(Dispatcher $dispatcher, OrdersRepository $ordersRepository)
    {
        $this->dispatcher = $dispatcher;
        $this->ordersRepository = $ordersRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['order_id'])) {
            throw new \Exception('unable to handle event: order_id missing');
        }
        if (!isset($payload['order_status'])) {
            throw new \Exception('unable to handle event: order_status missing');
        }
        $orderId = $payload['order_id'];
        $orderStatus = $payload['order_status'];
        $order = $this->ordersRepository->find($orderId);

        if (!$order) {
            throw new \Exception("unable to handle event: order with ID=$orderId does not exist");
        }

        $params = array_filter([
            'order_id' => $order->id,
            'order_status' => $orderStatus,
            'payment_id' => $order->payment->id,
            'subscription_id' => $order->payment->subscription_id ?? null,
        ]);

        $this->dispatcher->dispatch('order_status_change', $order->payment->user_id, $params, [
            JobsRepository::CONTEXT_HERMES_MESSAGE_TYPE => $message->getType()
        ]);
        return true;
    }
}
