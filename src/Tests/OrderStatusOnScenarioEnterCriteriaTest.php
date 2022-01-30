<?php

namespace Crm\ProductsModule\Tests;

use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Tests\TestPaymentConfig;
use Crm\ProductsModule\Events\OrderStatusChangeEvent;
use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\ProductsModule\Scenarios\OrderStatusChangeHandler;
use Crm\ProductsModule\Scenarios\OrderStatusOnScenarioEnterCriteria;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Repository\UsersRepository;

class OrderStatusOnScenarioEnterCriteriaTest extends BaseTestCase
{
    /** @var OrdersRepository */
    private $ordersRepository;

    private $user;

    private $subscriptionType;

    private $paymentGateway = false;

    private $scenariosCriteriaStorage;

    public function setUp(): void
    {
        parent::setUp();

        $this->scenariosCriteriaStorage = $this->inject(ScenariosCriteriaStorage::class);
        $this->scenariosCriteriaStorage->register(
            'trigger',
            OrderStatusOnScenarioEnterCriteria::KEY,
            $this->container->createInstance(OrderStatusOnScenarioEnterCriteria::class)
        );

        $this->ordersRepository = $this->getRepository(OrdersRepository::class);
    }

    public function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            OrdersRepository::class,
            UsersRepository::class
        ]);
    }

    public function testOrderStatusOnScenarioEnter()
    {
        $this->eventsStorage->register('order_status_change', OrderStatusChangeEvent::class, true);
        $this->dispatcher->registerHandler(
            'order-status-change',
            $this->container->createInstance(OrderStatusChangeHandler::class)
        );

        $this->createScenarioWithTrigger('order_status_change');
        $orderRow = $this->prepareOrder();

        // set status = paid, triggers scenario
        $this->ordersRepository->update($orderRow, [
            'status' => OrdersRepository::STATUS_PAID
        ]);

        // run Hermes to create trigger job
        $this->dispatcher->handle();

        // set status = not-sent, to check if are using order status on enter
        $this->ordersRepository->update($orderRow, [
            'status' => OrdersRepository::STATUS_NOT_SENT
        ]);

        $this->engine->run(3); // process trigger, finish its job and create wait job, job(condition): created -> started

        $this->dispatcher->handle(); // job(condition): started -> finished

        /** @var JobsRepository $jobsRepository */
        $jobsRepository = $this->getRepository(JobsRepository::class);
        $count = $jobsRepository->getFinishedJobs()->count('*');

        $this->assertEquals(1, $count);
        $finishedConditionJob = $jobsRepository->getFinishedJobs()->fetch();
        $result = json_decode($finishedConditionJob->result, true);
        $this->assertEquals(['conditions_met' => true], $result);
    }

    public function testOrderStatusOnScenarioEnterMissingOrderStatusException()
    {
        $this->eventsStorage->register('user_registered', UserRegisteredEvent::class, true);

        // test criteria connected to user_registered trigger (which doesn't have order_status parameter)
        $this->createScenarioWithTrigger('user_registered');
        $orderRow = $this->prepareOrder();

        // run Hermes to create trigger job
        $this->dispatcher->handle();

        $this->engine->run(3); // process trigger, finish its job and create wait job, job(condition): created -> started

        $this->dispatcher->handle(); // job(condition): started -> failed

        /** @var JobsRepository $jobsRepository */
        $jobsRepository = $this->getRepository(JobsRepository::class);
        $count = $jobsRepository->getFailedJobs()->count('*');
        $this->assertEquals(1, $count);

        $failedJob = $jobsRepository->getFailedJobs()->fetch();
        $result = json_decode($failedJob->result, true);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('order_status', $result['error']);
    }

    private function createScenarioWithTrigger($triggerCode)
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => $triggerCode],
                    'elements' => ['element_order_status_on_scenario']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_order_status_on_scenario',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'code' => 'tests_all_users',
                        'descendants' => [],
                        'conditions' => [
                            'event' => 'trigger',
                            'version' => 1,
                            'nodes' => [
                                [
                                    'key' => 'order_status_on_scenario_enter',
                                    'params' => [
                                        [
                                            'key' => 'order_status_on_scenario_enter',
                                            'values' => [
                                                'selection'=> ['paid'],
                                                'operator' => 'or'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]),
            ]
        ]);
    }

    private function prepareOrder()
    {
        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $user = $this->getUser();

        $payment = $paymentsRepository->add(
            $this->getSubscriptionType(),
            $this->getPaymentGateway(),
            $user,
            new PaymentItemContainer(),
            null,
            10
        );

        $orderRow = $this->ordersRepository->add(
            $payment,
            null,
            null,
            null,
            null
        );

        return $orderRow;
    }

    protected function getUser()
    {
        if (!$this->user) {
            /** @var UserManager $userManager */
            $userManager = $this->inject(UserManager::class);
            $this->user = $userManager->addNewUser('asfsaoihf@afasf.sk');
        }
        return $this->user;
    }

    protected function getSubscriptionType()
    {
        if (!$this->subscriptionType) {
            $subscriptionTypeBuilder = $this->container->getByType('Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder');
            $this->subscriptionType = $subscriptionTypeBuilder->createNew()
                ->setName('my subscription type')
                ->setUserLabel('my subscription type')
                ->setPrice(12.2)
                ->setCode('my_subscription_type')
                ->setLength(31)
                ->setActive(true)
                ->save();
        }
        return $this->subscriptionType;
    }

    protected function getPaymentGateway()
    {
        if (!$this->container->hasService('my_payConfig')) {
            $this->container->addService('my_payConfig', new TestPaymentConfig());
        }
        if (!$this->paymentGateway) {
            $paymentGatewaysRepository = $this->container->getByType('Crm\PaymentsModule\Repository\PaymentGatewaysRepository');
            $this->paymentGateway = $paymentGatewaysRepository->add('MyPay', 'my_pay');
        }
        return $this->paymentGateway;
    }
}
