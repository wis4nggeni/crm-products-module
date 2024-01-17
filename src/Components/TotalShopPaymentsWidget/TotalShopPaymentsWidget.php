<?php

namespace Crm\ProductsModule\Components\TotalShopPaymentsWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;

class TotalShopPaymentsWidget extends BaseLazyWidget
{
    private $templateName = 'total_shop_payments.latte';

    public function identifier()
    {
        return 'totalshoppaymentswidget';
    }

    public function render($stats)
    {
        if (!isset($stats['product_payments']) || empty($stats['product_payments']) || !isset($stats['product_payments_amount']) || empty($stats['product_payments_amount'])) {
            return;
        }

        $this->template->productPayments = $stats['product_payments'];
        $this->template->productPaymentsAmount = $stats['product_payments_amount'];
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
