<?php

namespace Crm\ProductsModule\Presenters;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\DataProvider\TrackerDataProviderInterface;
use Crm\ProductsModule\Ebook\EbookProvider;
use Crm\ProductsModule\Events\CartItemAddedEvent;
use Crm\ProductsModule\Events\CartItemRemovedEvent;
use Crm\ProductsModule\Forms\CheckoutFormFactory;
use Crm\ProductsModule\PaymentItem\PaymentItemHelper;
use Crm\ProductsModule\PostalFeeCondition\PostalFeeService;
use Crm\ProductsModule\Repository\PostalFeesRepository;
use Crm\ProductsModule\Repository\ProductsRepository;
use Crm\ProductsModule\Repository\TagsRepository;
use Nette\Application\BadRequestException;
use Nette\Forms\Controls\RadioList;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Emitter;

class ShopPresenter extends FrontendPresenter
{
    const SALES_FUNNEL_SHOP = 'shop';

    private $productsRepository;

    private $postalFeesRepository;

    private $checkoutFormFactory;

    private $paymentsRepository;

    private $paymentItemHelper;

    private $tagsRepository;

    private $paymentProcessor;

    private $ebookProvider;

    private $hermesEmitter;

    private $dataProviderManager;

    private $postalFeeService;

    private $cartSession;
    private $cartProducts;
    private $cartProductSum;

    public function __construct(
        ProductsRepository $productsRepository,
        PostalFeesRepository $postalFeesRepository,
        CheckoutFormFactory $checkoutFormFactory,
        PaymentsRepository $paymentsRepository,
        PaymentItemHelper $paymentItemHelper,
        TagsRepository $tagsRepository,
        PaymentProcessor $paymentProcessor,
        EbookProvider $ebookProvider,
        Emitter $hermesEmitter,
        DataProviderManager $dataProviderManager,
        PostalFeeService $postalFeeService
    ) {
        parent::__construct();

        $this->productsRepository = $productsRepository;
        $this->postalFeesRepository = $postalFeesRepository;
        $this->checkoutFormFactory = $checkoutFormFactory;
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentItemHelper = $paymentItemHelper;
        $this->tagsRepository = $tagsRepository;
        $this->paymentProcessor = $paymentProcessor;
        $this->ebookProvider = $ebookProvider;
        $this->hermesEmitter = $hermesEmitter;
        $this->dataProviderManager = $dataProviderManager;
        $this->postalFeeService = $postalFeeService;
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

        $this->template->layoutName = $this->layoutManager->getLayout($this->getLayout());
        $this->template->headerCode = $this->applicationConfig->get('shop_header_block');
        $this->template->ogImageUrl = $this->applicationConfig->get('shop_og_image_url');
    }

    protected function getLayoutName()
    {
        $layoutName = $this->applicationConfig->get('layout_name');
        if ($layoutName) {
            return $layoutName;
        }

        return 'shop';
    }

    protected function beforeRender()
    {
        parent::beforeRender();

        $this->template->cartProductSum = $this->cartProductSum;
        $this->template->currency = $this->applicationConfig->get('currency');
    }

    private function productListData()
    {
        $this->template->tags = $this->tagsRepository->all()->where(['visible' => true]);
        $counts = [];
        foreach ($this->tagsRepository->counts()->where(['shop' => true]) as $count) {
            $counts[$count->id] = $count->val;
        }
        $this->template->tagCounts = $counts;
        $this->template->productsCount = $this->productsRepository->getShopProducts(true, true)->count('*');
    }

    public function renderDefault()
    {
        // TODO: remove this fallback for deprecated routing by 2022
        if ($tags = $this->getParameter('tags')) {
            if (count($tags) === 1) {
                $tag = $this->tagsRepository->find(array_key_first($tags));
                if ($tag) {
                    $this->redirect('tag', ['tagCode' => $tag->code]);
                }
            }
            $this->redirect('this');
        }

        $this->setView('productList');
        $this->productListData();

        $this->template->title = $this->translator->translate('products.frontend.shop.default.header');
        $this->template->products = $this->productsRepository->getShopProducts(true, true);
        $this->template->selectedTag = null;
        $this->template->cartProducts = $this->cartSession->products;
    }

    public function renderTag($tagCode)
    {
        $tag = $this->tagsRepository->findBy('code', $tagCode);
        if (!$tag) {
            throw new BadRequestException("Tag not found: " . $tagCode, 404);
        }

        $this->setView('productList');
        $this->productListData();

        $this->template->title = $tag->name;
        $this->template->products = $this->productsRepository->getShopProducts(false, true, $tag);
        $this->template->selectedTag = $tag;
        $this->template->cartProducts = $this->cartSession->products;
    }

    public function renderShow($id, $code)
    {
        $product = $this->productsRepository->find($id);
        if (!$product && $code) {
            $product = $this->productsRepository->getByCode($code);
        }
        if (!$product || !$product->shop) {
            throw new BadRequestException('Product not found.', 404);
        }

        if (!$id || !$code) {
            $this->canonicalize('this', ['id' => $product->id, 'code' => $product->code]);
        }

        if ($code !== $product->code) {
            throw new BadRequestException("Product code does not match the product ID. Is your URL valid?", 404);
        }

        $this->template->now = new DateTime();
        $this->template->product = $product;
        $this->template->cartProducts = $this->cartSession->products;
    }

    public function actionAddToCart($id)
    {
        $this->handleAddCart($id);
    }

    public function handleAddCart($id, $redirectToCheckout = false)
    {
        $product = $this->productsRepository->find($id);
        if (!$product || !$product->shop) {
            throw new BadRequestException('Product not found.', Response::S404_NOT_FOUND);
        }

        if ($product->stock <= 0) {
            $this->flashMessage($product->name, 'product-not-available');
            $this->redirect('this');
        }

        if (isset($this->cartSession->products[$product->id]) && $product->stock <= $this->cartSession->products[$product->id]) {
            $this->flashMessage($product->name, 'product-more-not-available');
            $this->redirect('this');
        }

        if (!isset($this->cartSession->products[$product->id])) {
            if ($this->user->isLoggedIn() && $product->unique_per_user && $this->paymentItemHelper->hasUniqueProduct($product, $this->user->getId())) {
                $this->flashMessage($product->name, 'product-exists');
                $this->redirect('this');
            }

            $this->cartSession->products[$product->id] = 0;
        }

        if ($product->unique_per_user) {
            $this->cartSession->products[$product->id] = 0;
        }

        // fast checkout could mislead users if they already had something in their cart
        if ($redirectToCheckout) {
            $this->cartSession->products = [];
            $this->cartSession->products[$product->id] = 0;
            $this->cartSession->freeProducts = [];
        }

        $this->cartSession->products[$product->id]++;

        $this->emitter->emit(new CartItemAddedEvent($product));

        if ($this->isAjax() && !$redirectToCheckout) {
            $this->buildSession();
            $this->redrawControl('cart');
            $this->redrawControl('cartIcon');
        } else {
            $this->flashMessage($product->name, 'add-cart');
            $redirect = $redirectToCheckout ? 'checkout' : 'cart';
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
            if ($product->stock <= 0 || $product->stock < $this->cartSession->products[$product->id]) {
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
        $outOfStockProducts = [];
        $littleStockProducts = [];
        $amount = 0;

        foreach ($products as $product) {
            if ($product->stock <= 0) {
                unset($this->cartSession->products[$product->id]);
                $outOfStockProducts[] = $product->name;
            } elseif ($product->stock < $this->cartSession->products[$product->id]) {
                $this->cartSession->products[$product->id] = $product->stock;
                $littleStockProducts[] = $product->name;
            }
            $amount += $product->price * $this->cartProducts[$product->id];
        }

        if (!empty($outOfStockProducts) || !empty($littleStockProducts)) {
            if (!empty($outOfStockProducts)) {
                $this->flashMessage(implode(', ', $outOfStockProducts), 'product-out-of-stock');
            }
            if (!empty($littleStockProducts)) {
                $this->flashMessage(implode(', ', $littleStockProducts), 'product-little-in-stock');
            }

            $this->redirect('cart');
        }

        $freeProducts = [];
        if (count($this->cartSession->freeProducts)) {
            $freeProducts = $this->productsRepository->findByIds(array_keys($this->cartSession->freeProducts));
        }

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
            $this->hermesEmitter->emit(
                new HermesMessage(
                    'sales-funnel',
                    array_merge($this->getTrackerParams(), [
                        'type' => 'checkout',
                        'user_id' => $userId,
                        'sales_funnel_id' => self::SALES_FUNNEL_SHOP,
                    ])
                ),
                HermesMessage::PRIORITY_DEFAULT
            );
        };
        $this->checkoutFormFactory->onSave = function ($payment) {
            $trackerParams = $this->getTrackerParams();

            $this->paymentsRepository->addMeta($payment, $trackerParams);

            $eventParams = [
                'type' => 'payment',
                'user_id' => $payment->user_id,
                'sales_funnel_id' => self::SALES_FUNNEL_SHOP,
                'payment_id' => $payment->id,
            ];
            $this->hermesEmitter->emit(
                new HermesMessage(
                    'sales-funnel',
                    array_merge($eventParams, $trackerParams)
                ),
                HermesMessage::PRIORITY_DEFAULT
            );

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
            $options = $this->postalFeeService->getAvailablePostalFeesOptions($value, $this->cartProducts);
            $this['checkoutForm']['postal_fee']
                ->setItems($options)
                ->setDefaultValue($this->postalFeeService->getDefaultPostalFee($value, $options));
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

    public function renderError()
    {
        $this->template->contactEmail = $this->applicationConfig->get('contact_email');
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

    protected function getTrackerParams()
    {
        $trackerParams = [];

        /** @var TrackerDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'products.dataprovider.tracker',
            TrackerDataProviderInterface::class
        );
        foreach ($providers as $sorting => $provider) {
            $trackerParams[] = $provider->provide();
        }
        return array_merge([], ...$trackerParams);
    }
}
