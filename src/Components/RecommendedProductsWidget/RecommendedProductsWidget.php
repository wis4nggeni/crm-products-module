<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\ProductsModule\Repository\ProductsRepository;

class RecommendedProductsWidget extends BaseWidget
{
    private $templateName = 'recommended_products_widget.latte';

    private $productsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        ProductsRepository $productsRepository
    ) {
        parent::__construct($widgetManager);
        $this->productsRepository = $productsRepository;
    }

    public function identifier()
    {
        return 'recommendedproductswidget';
    }

    public function render($product)
    {
        $relatedProducts = $this->productsRepository->relatedProducts($product)->fetchAll();

        $this->template->relatedProducts = $relatedProducts;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
