<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UsersRepository;

class TotalShopPaymentsWidget extends BaseWidget
{
    private $templateName = 'total_shop_payments.latte';

    private $usersRepository;

    public function __construct(
        WidgetManager $widgetManager,
        UsersRepository $usersRepository
    ) {
        parent::__construct($widgetManager);

        $this->usersRepository = $usersRepository;
    }

    public function identifier()
    {
        return 'totalshoppaymentswidget';
    }

    public function render($meta)
    {
        if (!isset($meta['product_payments']) || !isset($meta['product_payments_amount'])) {
            return;
        }

        $this->template->meta = $meta;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
