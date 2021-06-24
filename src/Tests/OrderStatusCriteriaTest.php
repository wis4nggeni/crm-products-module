<?php

namespace Crm\ProductsModule\Tests;

use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\ProductsModule\Scenarios\OrderStatusCriteria;

class OrderStatusCriteriaTest extends PaymentsTestCase
{
    public function requiredRepositories(): array
    {
        $repositories = parent::requiredRepositories();
        $repositories[] = OrdersRepository::class;
        return $repositories;
    }

    public function dataProvider()
    {
        return [
            [
                'hasStatus' => OrdersRepository::STATUS_NEW,
                'shouldHaveStatus' => [OrdersRepository::STATUS_NEW],
                true
            ],
            [
                'hasStatus' => OrdersRepository::STATUS_NEW,
                'shouldHaveStatus' => [OrdersRepository::STATUS_NEW, OrdersRepository::STATUS_CONFIRMED],
                true
            ],
            [
                'hasStatus' => OrdersRepository::STATUS_NEW,
                'shouldHaveStatus' => [OrdersRepository::STATUS_PAID, OrdersRepository::STATUS_CONFIRMED],
                false
            ],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testOrderStatus(string $hasStatus, array $shouldHaveStatus, bool $result)
    {
        [$orderSelection, $orderRow] = $this->prepareData($hasStatus);

        $hasOrderCriteria = $this->inject(OrderStatusCriteria::class);
        $values = (object)['selection' => $shouldHaveStatus];
        $hasOrderCriteria->addConditions($orderSelection, [OrderStatusCriteria::KEY => $values], $orderRow);

        if ($result) {
            $this->assertNotEmpty($orderSelection->fetch());
        } else {
            $this->assertEmpty($orderSelection->fetch());
        }
    }

    private function prepareData(string $withStatus)
    {
        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        /** @var OrdersRepository $ordersRepository */
        $ordersRepository = $this->getRepository(OrdersRepository::class);

        $user = $this->getUser();

        $payment = $paymentsRepository->add(
            $this->getSubscriptionType(),
            $this->getPaymentGateway(),
            $user,
            new PaymentItemContainer(),
            null,
            10
        );

        $orderRow = $ordersRepository->add(
            $payment,
            null,
            null,
            null,
            null
        );

        $ordersRepository->update($orderRow, [
            'status' => $withStatus
        ]);

        $orderSelection = $ordersRepository->getTable()->where('id = ?', $orderRow->id);
        return [$orderSelection, $orderRow];
    }
}
