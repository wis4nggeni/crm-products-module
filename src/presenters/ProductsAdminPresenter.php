<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\Forms\ProductsFormFactory;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Crm\ProductsModule\Repository\ProductsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Http\Request;

class ProductsAdminPresenter extends AdminPresenter
{
    private $request;

    private $productsRepository;

    private $productsFormFactory;

    private $paymentItemsRepository;

    public function __construct(
        Request $request,
        ProductsRepository $productsRepository,
        ProductsFormFactory $productsFormFactory,
        PaymentItemsRepository $paymentItemsRepository
    ) {
        parent::__construct();
        $this->request = $request;
        $this->productsRepository = $productsRepository;
        $this->productsFormFactory = $productsFormFactory;
        $this->paymentItemsRepository = $paymentItemsRepository;
    }

    public function renderDefault()
    {
        $products = $this->productsRepository->all();

        $filteredCount = $this->template->filteredCount = $products->count('*');

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'products_vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);

        $this->template->vp = $vp;
        $this->template->products = $products->limit($paginator->getLength(), $paginator->getOffset());
    }

    public function renderShow($id)
    {
        $product = $this->productsRepository->find($id);
        if (!$product) {
            $this->flashMessage($this->translator->translate('products.admin.products.messages.product_not_found'));
            $this->redirect('default');
        }

        $levels = [0, 0.01, 3, 6, 10, 20, 50, 100, 200, 300];
        $this->template->amountSpentDistributionLevels = $levels;
        $this->template->amountSpentDistribution = $this->productsRepository->userAmountSpentDistribution($levels, $product->id);

        $levels = [0, 1, 3, 5, 8, 13, 21, 34];
        $this->template->paymentCountDistributionLevels = $levels;
        $this->template->paymentCountDistribution = $this->productsRepository->userPaymentCountsDistribution($levels, $product->id);

        $levels = [0, 1, 3, 5, 8, 13, 21, 34];
        $this->template->shopCountsDistributionLevels = $levels;
        $this->template->shopCountsDistribution = $this->productsRepository->productShopCountsDistribution($levels, $product->id);

        $levels = [0, 7, 14, 31, 93, 186, 365, 99999];
        $this->template->shopDaysDistribution = $this->productsRepository->productDaysFromLastOrderDistribution($levels, $product->id);

        $this->template->product = $product;

        $this->template->soldCount = $this->getProductSalesCount($product);
    }

    public function renderNew()
    {
    }

    private function getProductSalesCount(IRow $product)
    {
        return $this->paymentItemsRepository->getTable()
            ->where('product_id', $product->id)
            ->where('payment.status', PaymentsRepository::STATUS_PAID)
            ->fetchField('COALESCE(SUM(`count`), 0)');
    }

    public function renderUserList(int $id, string $type, float $fromLevel, float $toLevel = null)
    {
        $product = $this->productsRepository->find($id);
        if (!$product) {
            $this->flashMessage($this->translator->translate('products.admin.products.messages.product_not_found'), 'danger');
            $this->redirect('default');
        }
        $this->template->product = $product;
        $this->template->fromLevel = $fromLevel;
        $this->template->toLevel = $toLevel;
        $this->template->type = $type;

        if ($type == 'amountSpent') {
            $this->template->users = $this->productsRepository->userAmountSpentDistributionList($fromLevel, $toLevel, $id);
        } elseif ($type == 'paymentCounts') {
            $this->template->users = $this->productsRepository->userPaymentCountsDistributionList($fromLevel, $toLevel, $id);
        } elseif ($type == 'shopDays') {
            $this->template->users = $this->productsRepository->productDaysFromLastOrderDistributionList($fromLevel, $toLevel, $id);
        } elseif ($type == 'shopCounts') {
            $this->template->users = $this->productsRepository->productShopCountsDistributionList($fromLevel, $toLevel, $id);
        } else {
            $this->redirect('show', $id);
        }
    }

    public function renderEdit($id)
    {
        $product = $this->productsRepository->find($id);
        if (!$product) {
            $this->flashMessage($this->translator->translate('products.admin.products.messages.product_not_found'));
            $this->redirect('default');
        }
        $this->template->product = $product;
    }

    protected function createComponentProductsForm()
    {
        $id = $this->getParameter('id');
        $form = $this->productsFormFactory->create($id);

        $this->productsFormFactory->onSave = function (ActiveRow $product) {
            $this->flashMessage($this->translator->translate('products.admin.products.messages.product_created'));
            $this->redirect('Show', $product->id);
        };
        $this->productsFormFactory->onUpdate = function (ActiveRow $product) {
            $this->flashMessage($this->translator->translate('products.admin.products.messages.product_updated'));
            $this->redirect('Show', $product->id);
        };

        return $form;
    }

    protected function createComponentSaleGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $product = $this->productsRepository->find($this->params['id']);

        $graphDataItem1 = new GraphDataItem();
        $graphDataItem1->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setJoin("LEFT JOIN payment_items ON payments.id = payment_items.payment_id AND payment_items.type = '" . ProductPaymentItem::TYPE . "'")
            ->setTimeField('created_at')
            ->setWhere('AND payments.status = \'paid\' AND payment_items.product_id=' . $product->id)
            ->setValueField('SUM(payment_items.count)')
            ->setStart('-1 month'))
            ->setName('Product ' . $product->user_label);

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('products.admin.products.stats.sold_products'))
            ->setGraphHelp($this->translator->translate('products.admin.products.stats.sold_products_in_time'))
            ->addGraphDataItem($graphDataItem1);

        return $control;
    }
}
