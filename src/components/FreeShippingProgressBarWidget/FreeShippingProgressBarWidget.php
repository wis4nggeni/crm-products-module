<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\ProductsModule\Model\ProductsTrait;
use Crm\ProductsModule\PostalFeeCondition\PostalFeeNumericConditionInterface;
use Crm\ProductsModule\PostalFeeCondition\PostalFeeService;
use Crm\ProductsModule\Repository\ProductsRepository;
use Crm\UsersModule\Repository\CountriesRepository;

class FreeShippingProgressBarWidget extends BaseWidget
{
    use ProductsTrait;

    private $templateName = 'free_shipping_progress_bar.latte';

    private $postalFeeService;

    private $countriesRepository;

    private $productsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        PostalFeeService $postalFeeService,
        CountriesRepository $countriesRepository,
        ProductsRepository $productsRepository
    ) {
        parent::__construct($widgetManager);

        $this->postalFeeService = $postalFeeService;
        $this->countriesRepository = $countriesRepository;
        $this->productsRepository = $productsRepository;
    }

    public function identifier()
    {
        return 'freeshippingprogressbarwidget';
    }

    public function render(array $cartProducts = [], int $countryId = null)
    {
        if (!$countryId) {
            $countryId = $this->countriesRepository->defaultCountry()->id;
        }

        if (count($cartProducts) === 0) {
            return;
        }

        $products = $this->productsRepository->findByIds(array_keys($cartProducts));
        if (!$this->hasDelivery($products)) {
            return;
        }

        $countryPostalFeeConditionRow = $this->postalFeeService->getRecommendedFreePostalFeeCondition($countryId);

        if (!$countryPostalFeeConditionRow) {
            return;
        }

        $postalFeeCondition = $this->postalFeeService->getRegisteredConditionByCode($countryPostalFeeConditionRow->code);
        if (!$postalFeeCondition instanceof PostalFeeNumericConditionInterface) {
            return;
        }

        $this->template->isReached = $postalFeeCondition->isReached($cartProducts, $countryPostalFeeConditionRow->value);
        $this->template->actualValue = $postalFeeCondition->getActualValue($cartProducts);
        $this->template->desiredValue = $countryPostalFeeConditionRow->value;

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
