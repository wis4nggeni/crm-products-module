<?php

namespace Crm\ProductsModule\Tests;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\ProductsModule\PostalFeeCondition\PostalFeeConditionInterface;
use Crm\ProductsModule\PostalFeeCondition\PostalFeeService;
use Crm\ProductsModule\Repository\CountryPostalFeesRepository;
use Crm\ProductsModule\Repository\CountryPostalFeeConditionsRepository;
use Crm\ProductsModule\Repository\PostalFeesRepository;
use Mockery\Mock;
use Nette\Database\Table\IRow;

class PostalFeeServiceTest extends DatabaseTestCase
{
    /** @var PostalFeesRepository postalFeesRepository */
    private $postalFeesRepository;

    /** @var CountryPostalFeesRepository countryPostalFeesRepository */
    private $countryPostalFeesRepository;

    /** @var CountryPostalFeeConditionsRepository */
    private $countryPostalFeeConditionsRepository;

    protected function requiredRepositories(): array
    {
        return [
            CountryPostalFeesRepository::class,
            PostalFeesRepository::class,
            CountryPostalFeeConditionsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->postalFeesRepository = $this->getRepository(PostalFeesRepository::class);
        $this->countryPostalFeesRepository = $this->getRepository(CountryPostalFeesRepository::class);
        $this->countryPostalFeeConditionsRepository = $this->getRepository(CountryPostalFeeConditionsRepository::class);
    }

    public function testGetAvailablePostalOptionsWithoutPostalFeeConditions(): void
    {
        $this->preparePostalFeeForCountry(164, 'posta_list', 1.99);
        $this->preparePostalFeeForCountry(164, 'dhl_parcel', 1.99);
        $this->preparePostalFeeForCountry(164, 'ups_parcel', 1.99);

        $this->preparePostalFeeForCountry(32, 'dhl_parcel', 1.99);
        $this->preparePostalFeeForCountry(32, 'ups_parcel', 1.99);

        /** @var PostalFeeService $postalFeeService */
        $postalFeeService = $this->inject(PostalFeeService::class);

        $postalFees = $postalFeeService->getAvailablePostalFeesOptions(164, []);

        $this->assertCount(3, $postalFees);
    }

    public function testGetAvailablePostalOptionsWithPostalFeeConditions(): void
    {
        $this->preparePostalFeeForCountry(164, 'posta_list', 1.99);

        $postalFeeRow = $this->preparePostalFeeForCountry(164, 'dhl_parcel', 1.99);
        $this->countryPostalFeeConditionsRepository->add($postalFeeRow, 'test_code', 120);

        $this->preparePostalFeeForCountry(164, 'ups_parcel', 1.99);


        /** @var PostalFeeConditionInterface|Mock $postalFeeConditionMock */
        $postalFeeConditionMock = \Mockery::mock(PostalFeeConditionInterface::class)
            ->shouldReceive('isReached')
            ->andReturnFalse()
            ->getMock();

        /** @var PostalFeeService $postalFeeService */
        $postalFeeService = $this->inject(PostalFeeService::class);
        $postalFeeService->registerCondition('test_code', $postalFeeConditionMock);

        $postalFees = $postalFeeService->getAvailablePostalFeesOptions(164, []);

        $this->assertCount(2, $postalFees);
        $this->assertEquals(['posta_list', 'ups_parcel'], array_column($postalFees, 'code'));

        /** @var PostalFeeConditionInterface|Mock $postalFeeConditionMock */
        $postalFeeConditionMock = \Mockery::mock(PostalFeeConditionInterface::class)
            ->shouldReceive('isReached')
            ->andReturnTrue()
            ->getMock();

        $postalFeeService = new PostalFeeService($this->countryPostalFeesRepository);
        $postalFeeService->registerCondition('test_code', $postalFeeConditionMock);

        $postalFees = $postalFeeService->getAvailablePostalFeesOptions(164, []);
        $this->assertCount(3, $postalFees);
    }

    public function testGetAvailablePostalOptionsWithMorePostalFeesOfSameType(): void
    {
        $this->preparePostalFeeForCountry(164, 'posta_list', 1.99);
        $this->preparePostalFeeForCountry(164, 'dhl_parcel', 1.99);
        $this->preparePostalFeeForCountry(164, 'ups_parcel', 1.99);

        $postalFeeRow = $this->preparePostalFeeForCountry(164, 'dhl_parcel', 0, 20);
        $this->countryPostalFeeConditionsRepository->add($postalFeeRow, 'test_code', 120);

        /** @var PostalFeeConditionInterface|Mock $postalFeeConditionMock */
        $postalFeeConditionMock = \Mockery::mock(PostalFeeConditionInterface::class)
            ->shouldReceive('isReached')
            ->andReturnTrue()
            ->getMock();

        /** @var PostalFeeService $postalFeeService */
        $postalFeeService = $this->inject(PostalFeeService::class);
        $postalFeeService->registerCondition('test_code', $postalFeeConditionMock);

        $postalFees = $postalFeeService->getAvailablePostalFeesOptions(164, []);
        $this->assertCount(3, $postalFees);
        $this->assertEquals([1.99, 1.99, 0], array_column($postalFees, 'amount'));
    }

    public function testGetCheapestFreePostalFee()
    {
        $this->preparePostalFeeForCountry(164, 'posta_list', 1.99);
        $this->preparePostalFeeForCountry(164, 'dhl_parcel', 1.99);
        $this->preparePostalFeeForCountry(164, 'ups_parcel', 1.99);

        $postalFeeRow = $this->preparePostalFeeForCountry(164, 'dhl_parcel', 0, 20);
        $this->countryPostalFeeConditionsRepository->add($postalFeeRow, 'test_code', 120);

        $postalFeeRow = $this->preparePostalFeeForCountry(164, 'ups_parcel', 0, 40);
        $countryPostalFeeConditionRow = $this->countryPostalFeeConditionsRepository->add($postalFeeRow, 'test_code', 90);

        $postalFeeRow = $this->preparePostalFeeForCountry(164, 'ups_parcel', 0, 50);
        $this->countryPostalFeeConditionsRepository->add($postalFeeRow, 'test_code', 110);

        /** @var PostalFeeService $postalFeeService */
        $postalFeeService = $this->inject(PostalFeeService::class);

        $closestFreePostalFeeCondition = $postalFeeService->getRecommendedFreePostalFeeCondition(164);

        $this->assertNotFalse($closestFreePostalFeeCondition);
        $this->assertEquals($countryPostalFeeConditionRow->id, $closestFreePostalFeeCondition->id);
    }

    public function testGetDefaultPostalFee()
    {
        /** @var PostalFeeService $postalFeeService */
        $postalFeeService = $this->inject(PostalFeeService::class);

        // From paid choose default
        $postalFee1Row = $this->preparePostalFeeForCountry(164, 'posta_list', 2.99, 10, false);
        $postalFee2Row = $this->preparePostalFeeForCountry(164, 'dhl_parcel', 2.99, 10, true);
        $postalFee3Row = $this->preparePostalFeeForCountry(164, 'ups_parcel', 2.99, 10, false);

        $defaultPostalFeeRow = $postalFeeService->getDefaultPostalFee(164, [
            $postalFee1Row->id => $postalFee1Row,
            $postalFee2Row->id => $postalFee2Row,
            $postalFee3Row->id => $postalFee3Row,
        ]);
        $this->assertEquals($postalFee2Row->id, $defaultPostalFeeRow->id);

        // From paid and free choose free
        $postalFee4Row = $this->preparePostalFeeForCountry(165, 'posta_list', 2.99, 10, true);
        $postalFee5Row = $this->preparePostalFeeForCountry(165, 'dhl_parcel', 2.99, 10, false);
        $postalFee6Row = $this->preparePostalFeeForCountry(165, 'ups_parcel', 0, 10, false);

        $defaultPostalFeeRow = $postalFeeService->getDefaultPostalFee(165, [
            $postalFee4Row->id => $postalFee4Row,
            $postalFee5Row->id => $postalFee5Row,
            $postalFee6Row->id => $postalFee6Row,
        ]);
        $this->assertEquals($postalFee6Row->id, $defaultPostalFeeRow->id);

        // From all free choose default free
        $postalFee7Row = $this->preparePostalFeeForCountry(166, 'posta_list', 0, 10, false);
        $postalFee8Row = $this->preparePostalFeeForCountry(166, 'dhl_parcel', 0, 10, true);
        $postalFee9Row = $this->preparePostalFeeForCountry(166, 'ups_parcel', 0, 10, false);

        $defaultPostalFeeRow = $postalFeeService->getDefaultPostalFee(166, [
            $postalFee7Row->id => $postalFee7Row,
            $postalFee8Row->id => $postalFee8Row,
            $postalFee9Row->id => $postalFee9Row,
        ]);
        $this->assertEquals($postalFee8Row->id, $defaultPostalFeeRow->id);
    }
    
    private function preparePostalFeeForCountry(int $countryId, string $code, float $amount, int $sorting = 10, bool $default = false): IRow
    {
        $postalFeeRow = $this->postalFeesRepository->add($code, $code, $amount);
        $this->countryPostalFeesRepository->add($countryId, $postalFeeRow->id, $sorting, $default);

        return $postalFeeRow;
    }
}
