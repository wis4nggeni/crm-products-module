<?php

namespace Crm\ProductsModule\Presenters;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\Ebook\EbookProvider;
use Crm\ProductsModule\Events\CartItemAddedEvent;
use Crm\ProductsModule\Events\CartItemRemovedEvent;
use Crm\ProductsModule\Forms\CheckoutFormFactory;
use Crm\ProductsModule\PaymentItem\PaymentItemHelper;
use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\ProductsModule\Repository\PostalFeesRepository;
use Crm\ProductsModule\Repository\ProductsRepository;
use Crm\ProductsModule\Repository\TagsRepository;
use Nette\Application\BadRequestException;
use Nette\Forms\Controls\RadioList;
use Tomaj\Hermes\Emitter;
use Tracy\Debugger;

class ShopPresenter extends FrontendPresenter
{
    const SALES_FUNNEL_SHOP = 'shop';

    private $productsRepository;

    private $postalFeesRepository;

    private $ordersRepository;

    private $checkoutFormFactory;

    private $paymentsRepository;

    private $paymentItemsRepository;

    private $paymentItemHelper;

    private $tagsRepository;

    private $paymentProcessor;

    private $ebookProvider;

    private $hermesEmitter;

    private $cartSession;
    private $cartProducts;
    private $cartProductSum;

    /** @persistent */
    public $tags = [];

    public function __construct(
        ProductsRepository $productsRepository,
        PostalFeesRepository $postalFeesRepository,
        OrdersRepository $ordersRepository,
        CheckoutFormFactory $checkoutFormFactory,
        PaymentsRepository $paymentsRepository,
        PaymentItemsRepository $paymentItemsRepository,
        PaymentItemHelper $paymentItemHelper,
        TagsRepository $tagsRepository,
        PaymentProcessor $paymentProcessor,
        EbookProvider $ebookProvider,
        Emitter $hermesEmitter
    ) {
        parent::__construct();

        $this->productsRepository = $productsRepository;
        $this->postalFeesRepository = $postalFeesRepository;
        $this->ordersRepository = $ordersRepository;
        $this->checkoutFormFactory = $checkoutFormFactory;
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentItemsRepository = $paymentItemsRepository;
        $this->paymentItemHelper = $paymentItemHelper;
        $this->tagsRepository = $tagsRepository;
        $this->paymentProcessor = $paymentProcessor;
        $this->ebookProvider = $ebookProvider;
        $this->hermesEmitter = $hermesEmitter;
    }

    public function startup()
    {
        parent::startup();
        $this->buildCartSession();

        if ($this->layoutManager->exists($this->getLayoutName() . '_shop')) {
            $this->setLayout($this->getLayoutName() . '_shop');
        } else {
            $this->setLayout('shop');
        }

        $this->template->headerCode = $this->applicationConfig->get('shop_header_block');
    }

    protected function getLayoutName()
    {
        $layoutName = $this->applicationConfig->get('layout_name');
        if ($layoutName) {
            return $layoutName;
        }

        return 'shop';
    }

    public function renderDefault()
    {
        $this->template->cartProductSum = $this->cartProductSum;
        $this->template->tags = $this->tagsRepository->all()->where(['visible' => true]);
        $counts = [];
        foreach ($this->tagsRepository->counts() as $count) {
            $counts[$count->id] = $count->val;
        }
        $this->template->tagCounts = $counts;
        $this->template->productsCount = $this->productsRepository->getShopProducts(true, true)->count('*');
        $this->template->selectedTags = array_keys($this->tags);
        $this->template->products = $this->productsRepository->getShopProducts(empty($this->tags), true, array_keys($this->tags));
    }

    public function renderShow($id, string $code = null)
    {
        $product = $this->productsRepository->find($id);
        if (!$product || !$product->shop) {
            throw new BadRequestException('Product not found.', 404);
        }

        if ($code && $code !== $product->code) {
            Debugger::log("Provided code [{$code}] does not match code of provided product [{$id}].");
        }

        $this->template->cartProductSum = $this->cartProductSum;
        $this->template->product = $product;
        $this->template->relatedProducts = $this->productsRepository->relatedProducts($product);
    }

    public function handleAddCart($id, $checkout = false)
    {
        $product = $this->productsRepository->find($id);
        $redirect = $checkout ? 'checkout' : 'cart';

        if ($product->stock <= 0) {
            $this->flashMessage($product->name, 'product-not-available');
            $this->redirect($redirect);
        }

        if (!$product || !$product->shop) {
            throw new BadRequestException('Product not found.', 404);
        }

        if (!isset($this->cartSession->products[$product->id])) {
            if ($this->user->isLoggedIn() && $product->unique_per_user && $this->paymentItemHelper->hasUniqueProduct($product, $this->user->getId())) {
                $this->flashMessage($product->name, 'product-exists');
                $this->redirect($redirect);
            }

            $this->cartSession->products[$product->id] = 0;
        }

        if ($product->unique_per_user) {
            $this->cartSession->products[$product->id] = 0;
        }

        // fast checkout could mislead users if they already had something in their cart
        if ($checkout) {
            $this->cartSession->products = [];
            $this->cartSession->products[$product->id] = 0;
            $this->cartSession->freeProducts = [];
        }

        $this->cartSession->products[$product->id]++;

        $this->emitter->emit(new CartItemAddedEvent($product));

        if ($this->isAjax() && !$checkout) {
            $this->buildSession();
            $this->redrawControl('cart');
            $this->redrawControl('cartIcon');
        } else {
            $this->flashMessage($product->name, 'add-cart');
            $this->redirect($redirect);
        }
    }

    public function handleRemoveCart($id)
    {
        if ($this->request->isMethod('GET')) {
            $this->redirect('default');
        }

        $product = $this->productsRepository->find($id);
        if (!$product || !$product->shop) {
            throw new BadRequestException('Product not found.', 404);
        }

        if (!isset($this->cartSession->products[$product->id])) {
            throw new BadRequestException('Product not found.', 404);
        }

        $this->cartSession->products[$product->id]--;

        if ($this->cartSession->products[$product->id] == 0) {
            unset($this->cartSession->products[$product->id]);
        }

        $this->emitter->emit(new CartItemRemovedEvent($product));

        if ($this->isAjax()) {
            $this->buildSession();
            $this->redrawControl('cart');
            $this->redrawControl('cartIcon');
        }
    }

    public function handleRemoveProductCart($id)
    {
        if ($this->request->isMethod('GET')) {
            $this->redirect('default');
        }

        $product = $this->productsRepository->find($id);
        if (!$product || !$product->shop) {
            throw new BadRequestException('Product not found.', 404);
        }

        if (isset($this->cartSession->products[$product->id])) {
            unset($this->cartSession->products[$product->id]);
            $this->emitter->emit(new CartItemRemovedEvent($product));
        }

        if ($this->isAjax()) {
            $this->buildSession();
            $this->redrawControl('cart');
            $this->redrawControl('cartIcon');
        }
    }

    public function renderCart()
    {
        $products = $this->productsRepository->findByIds(array_keys($this->cartSession->products));
        $removedProducts = [];

        foreach ($products as $productKey => $product) {
            if ($product->stock <= 0) {
                unset($this->cartSession->products[$product->id]);
                unset($products[$productKey]);
                $this->cartProductSum = array_sum($this->cartSession->products);
                $removedProducts[] = $product->name;
            }
        }

        $freeProducts = [];
        if (is_array($this->cartSession->freeProducts) && count($this->cartSession->freeProducts)) {
            $freeProducts = $this->productsRepository->findByIds(array_keys($this->cartSession->freeProducts));
        }

        if (!empty($removedProducts)) {
            $this->flashMessage(implode(', ', $removedProducts), 'product-out-of-stock');
        }

        $this->template->cartProductSum = $this->cartProductSum;
        $this->template->cartProducts = $this->cartSession->products;
        $this->template->freeProducts = $freeProducts;
        $this->template->products = $products;
    }

    public function renderCheckout()
    {
        $products = $this->productsRepository->findByIds(array_keys($this->cartSession->products));
        $removedProducts = [];
        $amount = 0;

        foreach ($products as $product) {
            if ($product->stock <= 0) {
                unset($this->cartSession->products[$product->id]);
                $removedProducts[] = $product->name;
            }
            $amount += $product->price * $this->cartProducts[$product->id];
        }

        if (!empty($removedProducts)) {
            $this->flashMessage(implode(', ', $removedProducts), 'product-out-of-stock');
            $this->redirect('cart');
        }

        $userId = null;
        if ($this->getUser()->isLoggedIn()) {
            $userId = $this->getUser()->getId();
        }
        $browserId = (isset($_COOKIE['browser_id']) ? $_COOKIE['browser_id'] : null);

        if (!is_null($userId)) {
            $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
                'type' => 'checkout',
                'user_id' => $userId,
                'browser_id' => $browserId,
                'sales_funnel_id' => self::SALES_FUNNEL_SHOP,
                'source' => $this->trackingParams(),
            ]));
        }

        $freeProducts = [];
        if (count($this->cartSession->freeProducts)) {
            $freeProducts = $this->productsRepository->findByIds(array_keys($this->cartSession->freeProducts));
        }

        $this->template->cartProductSum = $this->cartProductSum;
        $this->template->cartProducts = $this->cartSession->products;
        $this->template->products = $products;
        $this->template->freeProducts = $freeProducts;
        $this->template->amount = $amount;
        $this->template->user = $this->getUser();
        $this->template->back = $this->storeRequest();
        $this->template->gatewayLabel = function ($gatewayCode) {
            return $this->checkoutFormFactory->gatewayLabel($gatewayCode);
        };
    }

    public function createComponentCheckoutForm()
    {
        $form = $this->checkoutFormFactory->create($this->cartProducts, $this->cartSession->freeProducts);
        $this->checkoutFormFactory->onLogin = function ($removeProducts) {
            if (empty($removeProducts)) {
                $this->redirect('checkout');
            } else {
                $products = [];
                foreach ($removeProducts as $product) {
                    unset($this->cartSession->products[$product->id]);
                    $products[] = $product->name;
                }
                $this->flashMessage(implode(', ', $products), 'product-removed');
                $this->redirect('cart');
            }
        };
        $this->checkoutFormFactory->onAuth = function ($userId) {
            $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
                'type' => 'checkout',
                'user_id' => $userId,
                'browser_id' => (isset($_COOKIE['browser_id']) ? $_COOKIE['browser_id'] : null),
                'sales_funnel_id' => self::SALES_FUNNEL_SHOP,
                'source' => $this->trackingParams(),
            ]));
        };
        $this->checkoutFormFactory->onSave = function ($payment) {
            $this->paymentsRepository->addMeta($payment, $this->trackingParams());

            $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
                'type' => 'payment',
                'user_id' => $payment->user_id,
                'browser_id' => (isset($_COOKIE['browser_id']) ? $_COOKIE['browser_id'] : null),
                'sales_funnel_id' => self::SALES_FUNNEL_SHOP,
                'payment_id' => $payment->id,
            ]));

            try {
                $this->paymentProcessor->begin($payment);
            } catch (CannotProcessPayment $err) {
                $this->redirect('error');
            }
        };

        return $form;
    }

    public function handleCountryChange($value)
    {
        if (!$value) {
            return;
        }
        if ($this['checkoutForm']['postal_fee'] instanceof RadioList) {
            $this['checkoutForm']['postal_fee']
                ->setItems($this->postalFeesRepository->getByCountry($value)->fetchAll())
                ->setDefaultValue($this->postalFeesRepository->getDefaultByCountry($value)->fetch());
        }

        $this->redrawControl('postalFeesSnippet');
        $this->redrawControl('cart');
    }

    public function handlePostalFeeChange($value)
    {
        if (!$value) {
            return;
        }
        $this['checkoutForm']['postal_fee']
            ->setDefaultValue($value);

        $this->redrawControl('cart');
    }

    public function renderSuccess($id)
    {
        $payment = $this->paymentsRepository->findByVs($id);
        if ($payment->user_id != $this->user->getId()) {
            $this->flashMessage('Odkaz nie je platný. Boli ste presmerovaný naspäť na obchod.', 'error');
            $this->redirect('default');
        }

        $order = $payment->related('orders')->fetch();
        $address = $order->ref('addresses', 'shipping_address_id');
        if (!$address) {
            $address = $order->ref('addresses', 'licence_address_id');
        }
        if (!$address) {
            $address = $order->ref('addresses', 'billing_address_id');
        }

        $ebooks = [];

        if ($address) {
            $this->paymentItemHelper->unBundleProducts($order->payment, function ($product) use ($address, &$ebooks) {
                if (!isset($ebooks[$product->id])) {
                    $user = $this->usersRepository->find($this->user->getIdentity()->getId());
                    $links = $this->ebookProvider->getDownloadLinks($product, $user, $address);
                    if (!empty($links)) {
                        $ebooks[$product->id] = [
                            'product' => $product,
                            'links' => $links,
                        ];
                    }
                }
            });
        }

        $fileFormatMap = $this->ebookProvider->getFileTypes();

        $this->template->payment = $payment;
        $this->template->order = $order;
        $this->template->fileFormatMap = $fileFormatMap;
        $this->template->ebooks = $ebooks;

        $this->cartSession->products = [];
    }

    private function buildCartSession()
    {
        $this->cartSession = $this->getSession('cart');
        if (!isset($this->cartSession->products)) {
            $this->cartSession->products = [];
        }
        if (!isset($this->cartSession->freeProducts)) {
            $this->cartSession->freeProducts = [];
        }

        $this->cartProducts = $this->cartSession->products;
        $this->cartProductSum = array_sum($this->cartProducts);
    }

    private function buildSession()
    {
        $this->buildCartSession();
        $this->buildTrackingParamsSession();
    }

    public function handleAddTag()
    {
        $tag = $this->getParameter('tag');
        if (!$tag) {
            return;
        }
        $this->tags[$tag] = $this->getParameter('code', true);
        $this->redirect('default');
    }

    public function handleRemoveTag()
    {
        $tag = $this->getParameter('tag');
        if (!$tag) {
            return;
        }
        unset($this->tags[$tag]);
        $this->redirect('default');
    }

    public function handleClearTags()
    {
        $this->tags = [];
        $this->redirect('default');
    }
}
