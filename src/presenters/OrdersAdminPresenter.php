<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\Forms\CheckoutFormFactory;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\ProductsModule\Repository\PostalFeesRepository;
use Crm\ProductsModule\Repository\ProductsRepository;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\RadioList;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;
use Tomaj\Form\Renderer\BootstrapRenderer;

class OrdersAdminPresenter extends AdminPresenter
{
    private $ordersRepository;

    private $paymentsRepository;

    private $postalFeesRepository;

    private $productsRepository;

    public $checkoutFormFactory;

    /** @persistent */
    public $payment_status;

    /** @persistent */
    public $order_status;

    public function __construct(
        OrdersRepository $ordersRepository,
        PaymentsRepository $paymentsRepository,
        PostalFeesRepository $postalFeesRepository,
        ProductsRepository $productsRepository,
        CheckoutFormFactory $checkoutFormFactory
    ) {
        parent::__construct();
        $this->ordersRepository = $ordersRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->postalFeesRepository = $postalFeesRepository;
        $this->productsRepository = $productsRepository;
        $this->checkoutFormFactory = $checkoutFormFactory;
    }

    public function renderDefault()
    {
        $orders = $this->getFilteredOrders();
        $filteredCount = $orders->count('*');
        $totalCount = $this->ordersRepository->totalCount();

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);

        $this->template->vp = $vp;
        $this->template->orders = $orders->limit($paginator->getLength(), $paginator->getOffset());
        $this->template->filteredCount = $filteredCount;
        $this->template->totalCount = $totalCount;
    }

    public function renderNew($paymentId)
    {
        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            $this->redirect('default');
        }
        foreach ($payment->related('orders') as $order) {
            $this->flashMessage($this->translator->translate('products.admin.orders.default.warnings.order_for_payment_exists'), 'warning');
            $this->redirect('show', $order->id);
        }
        $this->template->payment = $payment;
    }

    public function renderShow($id)
    {
        $order = $this->ordersRepository->find($id);

        $this->template->order = $order;
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->addText('text', $this->translator->translate('products.admin.orders.default.fields.order_id_vs') . ':')
            ->setAttribute('autofocus');

        $products = $this->productsRepository->getTable()->fetchPairs('id', 'name');
        $form->addMultiSelect('products', $this->translator->translate('products.admin.orders.default.fields.products'), $products)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSelect('payment_status', $this->translator->translate('products.admin.orders.default.fields.payment_state'), $this->paymentsRepository->getStatusPairs())->setPrompt('--');

        $form->addSelect('order_status', $this->translator->translate('products.admin.orders.default.fields.order_state'), $this->ordersRepository->getStatusPairs())->setPrompt('--');

        $form->addSubmit('send', $this->translator->translate('products.admin.orders.default.fields.filter'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('products.admin.orders.default.fields.filter'));
        $presenter = $this;
        $form->addSubmit('cancel', $this->translator->translate('products.admin.orders.default.fields.cancel_filter'))->onClick[] = function () use ($presenter) {
            $presenter->redirect('OrdersAdmin:Default', [
                'text' => '',
                'payment_status' => '',
                'order_status' => '',
                'products' => [],
            ]);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];
        $form->setDefaults([
            'text' => $this->text,
            'payment_status' => $this->payment_status,
            'order_status' => $this->order_status,
            'products' => $this->getParameter('products'),
        ]);
        return $form;
    }

    public function createComponentCheckoutForm()
    {
        $payment = $this->paymentsRepository->find($this->getParameter('paymentId'));
        $cart = [];
        foreach ($payment->related('payment_items')->where('type = ?', ProductPaymentItem::TYPE) as $paymentItem) {
            $cart[$paymentItem->product_id] = $paymentItem->count;
        }
        $form = $this->checkoutFormFactory->create($cart, null, $payment);
        $form->setRenderer(new BootstrapRenderer());
        $this->checkoutFormFactory->onSave = function ($payment) {
            $this->redirect('default');
        };
        return $form;
    }

    public function handleCountryChange($value)
    {
        if ($this['checkoutForm']['postal_fee'] instanceof RadioList) {
            $this['checkoutForm']['postal_fee']
                ->setItems($this->postalFeesRepository->getByCountry($value)->fetchAll())
                ->setDefaultValue($this->postalFeesRepository->getDefaultByCountry($value));
        }

        $this->redrawControl('postalFeesSnippet');
        $this->redrawControl('cart');
    }

    public function handlePostalFeeChange()
    {
        $value = $this->request->getPost('value');
        $this['checkoutForm']['postal_fee']
            ->setDefaultValue($value);

        $this->redrawControl('cart');
    }

    private function getFilteredOrders()
    {
        $orders = $this->ordersRepository->all();
        if (isset($this->params['payment_status'])) {
            $orders->where('payment.status', $this->params['payment_status']);
        }

        if (isset($this->params['order_status'])) {
            $orders->where('orders.status', $this->params['order_status']);
        }

        if (isset($this->params['text'])) {
            $orders->where('payment.variable_symbol LIKE ? OR orders.id LIKE ?', "%{$this->params['text']}%", "%{$this->params['text']}%");
        }

        if ($this->getParameter('products')) {
            if ($this->getParameter('products')) {
                $orders->where('payment:payment_items.product_id', $this->getParameter('products'));
            }
        }

        return $orders;
    }
}
