<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Components\DateFilterFormFactory;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\ProductsModule\Components\ProductStatsFactoryInterface;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Nette\Utils\DateTime;

class DashboardPresenter extends AdminPresenter
{
    /** @persistent */
    public $dateFrom;

    /** @persistent */
    public $dateTo;

    public function startup()
    {
        parent::startup();
        $this->dateFrom = $this->dateFrom ?? DateTime::from('-2 months')->format('Y-m-d');
        $this->dateTo = $this->dateTo ?? DateTime::from('today')->format('Y-m-d');
    }

    public function renderDefault()
    {
    }

    public function createComponentDateFilterForm(DateFilterFormFactory $dateFilterFormFactory)
    {
        $form = $dateFilterFormFactory->create($this->dateFrom, $this->dateTo);
        $form->onSuccess[] = function ($form, $values) {
            $this->dateFrom = $values['date_from'];
            $this->dateTo = $values['date_to'];
            $this->redirect($this->action);
        };
        return $form;
    }

    public function createComponentGoogleProductsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('created_at')
            ->setWhere("AND payments.status = 'paid' AND products.shop = 1")
            ->setGroupBy('products.id')
            ->setJoin(
                "LEFT JOIN payment_items ON payments.id = payment_items.payment_id AND payment_items.type = '" . ProductPaymentItem::TYPE . "' " .
                'LEFT JOIN products ON product_id = products.id'
            )
            ->setSeries('products.name')
            ->setValueField('SUM(payment_items.count)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.shop.products.title'))
            ->setGraphHelp($this->translator->translate('dashboard.shop.products.tooltip'))
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentTableProductsStatsGraph(ProductStatsFactoryInterface $factory)
    {
        $control = $factory->create();
        return $control;
    }
}
