<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Components\DateFilterFormFactory;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\ProductsModule\Components\ProductStatsFactory;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class DashboardPresenter extends AdminPresenter
{
    /** @persistent */
    public $dateFrom;

    /** @persistent */
    public $dateTo;

    /** @persistent */
    public $productStatsMode;

    /** @var ProductStatsFactory @inject */
    public $productStatsFactory;

    public function startup()
    {
        parent::startup();
        $this->dateFrom = $this->dateFrom ?? DateTime::from('-2 months')->format('Y-m-d');
        $this->dateTo = $this->dateTo ?? DateTime::from('today')->format('Y-m-d');
        $this->productStatsMode = $this->productStatsMode ?? ProductStatsFactory::MODE_ALL;
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
        $this->getSession()->close();
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('created_at')
            ->setWhere("AND payments.status = 'paid'")
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

    public function createComponentProductStatsModeForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->addSelect('product_stats_mode', $this->translator->translate('products.admin.products.stats.form.mode'), $this->productStatsFactory->getProductModesPairs());

        $form->addSubmit('send', $this->translator->translate('products.admin.products.stats.form.filter'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('products.admin.products.stats.form.filter'));

        $form->setDefaults([
            'product_stats_mode' => $this->productStatsMode,
        ]);
        $form->onSuccess[] = function ($form, $values) {
            $this->productStatsMode = $values['product_stats_mode'];
            $this->redirect($this->action);
        };
        return $form;
    }

    public function createComponentTableProductsStatsGraph()
    {
        return $this->productStatsFactory->create($this->productStatsMode);
    }
}
