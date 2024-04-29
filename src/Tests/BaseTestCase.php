<?php
declare(strict_types=1);

namespace Crm\ProductsModule\Tests;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Config\ConfigsCache;
use Crm\ApplicationModule\Models\Scenario\TriggerManager;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\PrintModule\Seeders\AddressTypesSeeder;
use Crm\ProductsModule\Models\ProductsCache;
use Crm\ProductsModule\Models\TagsCache;
use Crm\ProductsModule\ProductsModule;
use Crm\ProductsModule\Repositories\ProductsRepository;
use Crm\ProductsModule\Repositories\TagsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Repositories\UsersRepository;

abstract class BaseTestCase extends DatabaseTestCase
{
    protected ProductsModule $productsModule;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            SubscriptionsRepository::class,
            SubscriptionMetaRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            PaymentsRepository::class,
            PaymentGatewaysRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            PaymentGatewaysSeeder::class,
            CountriesSeeder::class,
            AddressTypesSeeder::class,
            \Crm\InvoicesModule\Seeders\AddressTypesSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();

        $this->productsModule = new ProductsModule(
            $this->container,
            $this->inject(Translator::class),
            $this->inject(ApplicationConfig::class),
            $this->inject(ConfigsCache::class),
            $this->inject(ProductsCache::class),
            $this->inject(ProductsRepository::class),
            $this->inject(TagsCache::class),
            $this->inject(TagsRepository::class),
        );
        $this->productsModule->registerScenariosTriggers($this->inject(TriggerManager::class));
    }
}
