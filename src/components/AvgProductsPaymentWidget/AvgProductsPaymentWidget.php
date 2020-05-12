<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UserMetaRepository;

class AvgProductsPaymentWidget extends BaseWidget
{
    private $templateName = 'avg_products_payment_widget.latte';

    private $userMetaRepository;

    public function __construct(WidgetManager $widgetManager, UserMetaRepository $userMetaRepository)
    {
        parent::__construct($widgetManager);
        $this->userMetaRepository = $userMetaRepository;
    }

    public function identifier()
    {
        return 'avgproductspaymentwidget';
    }

    public function render(array $userIds)
    {
        if (!count($userIds)) {
            return;
        }

        $result = $this->userMetaRepository
            ->getTable()
            ->select('COALESCE(SUM(value), 0) AS sum')
            ->where(['key' => 'product_payments', 'user_id' => $userIds])
            ->fetch();

        $this->template->avgProductPayments = $result->sum / count($userIds);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
