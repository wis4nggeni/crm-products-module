<?php

namespace Crm\ProductsModule\User;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\UsersModule\User\AddressesUserDataProvider;

class OrdersUserDataProvider implements UserDataProviderInterface
{
    private $ordersRepository;

    public function __construct(OrdersRepository $ordersRepository)
    {
        $this->ordersRepository = $ordersRepository;
    }

    public static function identifier(): string
    {
        return 'orders';
    }

    public function data($userId)
    {
        return [];
    }

    public function download($userId)
    {
        $orders = $this->ordersRepository->getByUser($userId);

        $results = [];
        foreach ($orders as $order) {
            $result = [
                'status' => $order->status,
                'created_at' => $order->created_at->format(\DateTime::RFC3339),
                'postal_service' => $order->postal_fee ? $order->postal_fee->title : null,
            ];

            $result['products'] = [];
            foreach ($order->payment->related('payment_items')->where('type = ?', ProductPaymentItem::TYPE) as $paymentItem) {
                $result['products'][] = [
                    'name' => $paymentItem->name,
                    'price' => $paymentItem->amount,
                    'count' => $paymentItem->count,
                ];
            }

            $results[] = $result;
        }

        return $results;
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    /**
     * Protect Addresses used by Orders against removal
     *
     * @param $userId
     * @return array
     * @throws \Exception
     */
    public function protect($userId): array
    {
        $exclude = [];
        foreach ($this->ordersRepository->getByUser($userId)->fetchAll() as $order) {
            $exclude[] = $order->shipping_address_id;
            $exclude[] = $order->licence_address_id;
            $exclude[] = $order->billing_address_id;
        }

        return [AddressesUserDataProvider::identifier() => array_unique(array_filter($exclude), SORT_NUMERIC)];
    }

    public function delete($userId, $protectedData = [])
    {
        return false;
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
