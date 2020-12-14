<?php

namespace Crm\ProductsModule\Forms;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProvider\CheckoutFormDataProviderInterface;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\PaymentItem\PaymentItemHelper;
use Crm\ProductsModule\PaymentItem\PostalFeePaymentItem;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Crm\ProductsModule\Repository\CountryPostalFeesRepository;
use Crm\ProductsModule\Repository\DistributionCentersRepository;
use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\ProductsModule\Repository\PostalFeesRepository;
use Crm\ProductsModule\Repository\ProductsRepository;
use Crm\UsersModule\Auth\Authorizator;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Security\AuthenticationException;
use Nette\Security\User;
use Nette\Utils\Html;
use Nette\Utils\Json;

class CheckoutFormFactory
{
    private $applicationConfig;

    private $gateways = [];

    private $paymentsRepository;

    private $paymentGatewaysRepository;

    private $productsRepository;

    private $user;

    private $usersRepository;

    private $userManager;

    private $addressesRepository;

    private $addressChangeRequestsRepository;

    private $countriesRepository;

    private $ordersRepository;

    private $postalFeesRepository;

    private $request;

    private $authorizator;

    private $translator;

    public $onSave;

    public $onLogin;

    public $onAuth;

    private $cartFree;

    private $paymentItemHelper;

    private $dataProviderManager;

    private $countryPostalFeesRepository;

    public function __construct(
        ApplicationConfig $applicationConfig,
        PaymentsRepository $paymentsRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository,
        ProductsRepository $productsRepository,
        User $user,
        UsersRepository $usersRepository,
        UserManager $userManager,
        AddressesRepository $addressesRepository,
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        CountriesRepository $countriesRepository,
        OrdersRepository $ordersRepository,
        PostalFeesRepository $postalFeesRepository,
        Request $request,
        Authorizator $authorizator,
        Translator $translator,
        PaymentItemHelper $paymentItemHelper,
        DataProviderManager $dataProviderManager,
        CountryPostalFeesRepository $countryPostalFeesRepository
    ) {
        $this->applicationConfig = $applicationConfig;
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->productsRepository = $productsRepository;
        $this->user = $user;
        $this->usersRepository = $usersRepository;
        $this->userManager = $userManager;
        $this->addressesRepository = $addressesRepository;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->countriesRepository = $countriesRepository;
        $this->ordersRepository = $ordersRepository;
        $this->postalFeesRepository = $postalFeesRepository;
        $this->request = $request;
        $this->authorizator = $authorizator;
        $this->translator = $translator;
        $this->paymentItemHelper = $paymentItemHelper;
        $this->dataProviderManager = $dataProviderManager;
        $this->countryPostalFeesRepository = $countryPostalFeesRepository;
    }

    public function create($cart, $cartFree = [], $payment = null)
    {
        $this->cartFree = $cartFree;
        $defaults = [];
        $countryId = $this->countriesRepository->defaultCountry()->id;

        if ($this->user->isLoggedIn()) {
            if ($payment) {
                $user = $payment->user;
            } else {
                $user = $this->userManager->loadUser($this->user);
            }

            $address = $this->addressesRepository->address($user, 'shop');
            if ($address) {
                $defaults['shipping_address'] = $address->toArray();
                $defaults['contact']['phone_number'] = $address->phone_number;
                $countryId = $address->country_id;
            }

            $address = $this->addressesRepository->address($user, 'invoice');
            if ($address) {
                $defaults['billing_address'] = $address->toArray();

                if (!isset($defaults['contact']['phone_number'])) {
                    $defaults['contact']['phone_number'] = $address->phone_number;
                }
            }
        }

        $postShippingAddress = $this->request->getPost('shipping_address');
        if (isset($postShippingAddress['country_id']) && $postShippingAddress['country_id'] !== null) {
            $countryId = $postShippingAddress['country_id'];
        }

        $products = $this->productsRepository->findByIds(array_keys($cart));
        $hasDelivery = false;
        $hasLicence = false;

        $flagHandler = function ($product) use (&$hasDelivery, &$hasLicence) {
            if ($product->has_delivery) {
                $hasDelivery = true;
            }
            if ($product->distribution_center == DistributionCentersRepository::DISTRIBUTION_CENTER_DIBUK) {
                $hasLicence = true;
            }
        };
        foreach ($products as $product) {
            if ($product->bundle) {
                foreach ($product->related('product_bundles') as $productBundle) {
                    $flagHandler($productBundle->item);
                }
            } else {
                $flagHandler($product);
            }
        }

        $form = new Form;

        $form->addHidden('cart', Json::encode($cart));
        $form->addHidden('cart_free', Json::encode($cartFree));
        $postalFee = $form->addHidden('postal_fee', false);
        $action = $form->addHidden('action', 'checkout');

        if (!$payment) {
            $paymentGateways = $this->paymentGatewaysRepository->getAllVisible()
                ->where(['code' => array_keys($this->gateways)])
                ->fetchPairs('id', 'code');
            $form->addRadioList('payment_gateway', null, $paymentGateways)
                ->setRequired($this->translator->translate('products.frontend.shop.checkout.choose_payment_method'));
        }

        $user = $form->addContainer('user');

        if ($this->user->isLoggedIn()) {
            $user->addHidden('email')
                ->setDefaultValue($this->user->getIdentity()->email);
        } else {
            $email = $user->addText('email', Html::el()->setHtml('Email<i id="preloader" class="fa fa-refresh fa-spin"></i>'));
            $email->setAttribute('placeholder', '@');
            $email->setRequired($this->translator->translate('products.frontend.shop.checkout.fields.email_required'));

            $emailUsable = function ($field, $args) {
                $user = $this->usersRepository->findBy('email', $field->getValue());
                return !$user;
            };
            $email->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule($emailUsable, $this->translator->translate('products.frontend.shop.checkout.fields.account_exists'));
        }

        $user->addPassword('password', 'Heslo')
            ->addConditionOn($action, Form::EQUAL, 'login')
            ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.pass_required'));

        $contact = $form->addContainer('contact');
        $contact->addText('phone_number', $this->translator->translate('products.frontend.shop.checkout.fields.phone_number'))
            ->setAttribute('placeholder', $this->translator->translate('products.frontend.shop.checkout.fields.phone_number_placeholder'))
            ->addConditionOn($action, Form::NOT_EQUAL, 'login')
            ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.phone_number_required'))
            ->addRule(Form::MIN_LENGTH, $this->translator->translate('products.frontend.shop.checkout.fields.phone_number_min_length'), 6);

        $invoice = $form->addContainer('invoice');
        $addInvoice = $invoice->addCheckbox('add_invoice', $this->translator->translate('products.frontend.shop.checkout.fields.want_invoice'));
        $addInvoice->getLabelPrototype()->addAttributes(['class' => 'checkbox-inline']);

        $sameShipping = $invoice->addCheckbox('same_shipping', $this->translator->translate('products.frontend.shop.checkout.fields.same_shipping'))
            ->setDefaultValue(true);
        $sameShipping->getLabelPrototype()->addAttributes(['class' => 'checkbox-inline', 'id' => 'same-address']);

        $addInvoice->addCondition(Form::EQUAL, true)
            ->toggle('same-address');

        if ($hasDelivery) {
            $form->removeComponent($postalFee);
            $form->addRadioList('postal_fee', null, $this->postalFeesRepository->getByCountry($countryId)->fetchAll())
                ->setRequired($this->translator->translate('products.frontend.shop.checkout.fields.choose_shipping_method'));

            $defaults['postal_fee'] = $this->postalFeesRepository->getDefaultByCountry($countryId)->fetch();

            $shippingAddress = $form->addContainer('shipping_address');
            $shippingAddress->addText('first_name', $this->translator->translate('products.frontend.shop.checkout.fields.first_name'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.first_name_required'));

            $shippingAddress->addText('last_name', $this->translator->translate('products.frontend.shop.checkout.fields.last_name'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.last_name_required'));

            $shippingAddress->addText('address', $this->translator->translate('products.frontend.shop.checkout.fields.street'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.street_required'))
                ->addRule(Form::MIN_LENGTH, $this->translator->translate('products.frontend.shop.checkout.fields.street_min_length'), 3);

            $shippingAddress->addText('number', $this->translator->translate('products.frontend.shop.checkout.fields.number'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.number_required'));

            $shippingAddress->addText('city', $this->translator->translate('products.frontend.shop.checkout.fields.city'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.city_required'));

            $shippingAddress->addText('zip', $this->translator->translate('products.frontend.shop.checkout.fields.zip_code'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.zip_code_required'));

            $availableCountryPairs = $this->countryPostalFeesRepository->findAllAvailableCountryPairs();
            $shippingAddress->addSelect('country_id', $this->translator->translate('products.frontend.shop.checkout.fields.country'), $availableCountryPairs);
            $sameShipping->addCondition(Form::EQUAL, true)
                ->toggle('billing-address', false);
        } elseif ($hasLicence) {
            $licenceAddress = $form->addContainer('licence_address');
            $licenceAddress->addText('first_name', $this->translator->translate('products.frontend.shop.checkout.fields.first_name'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.first_name_required'));

            $licenceAddress->addText('last_name', $this->translator->translate('products.frontend.shop.checkout.fields.last_name'))
                ->addConditionOn($action, Form::NOT_EQUAL, 'login')
                ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.first_name_required'));

            $sameShipping->setDisabled()
                ->setDefaultValue(false);

            $addInvoice->addCondition(Form::EQUAL, true)
                ->toggle('billing-address');
        }

        if (!$hasDelivery) {
            $sameShipping->setDisabled(true)
                ->setDefaultValue(false);

            $addInvoice->addCondition(Form::EQUAL, true)
                ->toggle('billing-address');
        }

        /** @var CheckoutFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('products.dataprovider.checkout_form', CheckoutFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide([
                'form' => $form,
                'action' => $action,
                'cart' => $cart,
            ]);
        }

        $billingAddress = $form->addContainer('billing_address');
        $billingAddress->addTextArea('company_name', $this->translator->translate('products.frontend.shop.checkout.fields.company_name'), null, 1)
            ->setMaxLength(150)
            ->addConditionOn($sameShipping->isDisabled() ? $addInvoice : $sameShipping, Form::EQUAL, $sameShipping->isDisabled() ? true : false)
            ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.company_name_required'));

        $billingAddress->addText('address', $this->translator->translate('products.frontend.shop.checkout.fields.street'));

        $billingAddress->addText('number', $this->translator->translate('products.frontend.shop.checkout.fields.number'))
            ->addConditionOn($sameShipping->isDisabled() ? $addInvoice : $sameShipping, Form::EQUAL, $sameShipping->isDisabled() ? true : false)
            ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.number_required'));

        $billingAddress->addText('city', $this->translator->translate('products.frontend.shop.checkout.fields.city'))
            ->addConditionOn($sameShipping->isDisabled() ? $addInvoice : $sameShipping, Form::EQUAL, $sameShipping->isDisabled() ? true : false)
            ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.city_required'));

        $billingAddress->addText('zip', $this->translator->translate('products.frontend.shop.checkout.fields.zip_code'))
            ->addConditionOn($sameShipping->isDisabled() ? $addInvoice : $sameShipping, Form::EQUAL, $sameShipping->isDisabled() ? true : false)
            ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.zip_code_required'));

        $billingAddress->addSelect('country_id', $this->translator->translate('products.frontend.shop.checkout.fields.country'), $this->countriesRepository->getAllPairs());

        $billingAddress->addText('company_id', $this->translator->translate('products.frontend.shop.checkout.fields.company_id'))
            ->setAttribute('placeholder', $this->translator->translate('products.frontend.shop.checkout.fields.company_id_placeholder'));

        $billingAddress->addText('company_tax_id', $this->translator->translate('products.frontend.shop.checkout.fields.company_tax_id'))
            ->setAttribute('placeholder', $this->translator->translate('products.frontend.shop.checkout.fields.company_tax_id_placeholder'));

        $billingAddress->addText('company_vat_id', $this->translator->translate('products.frontend.shop.checkout.fields.company_vat_id'))
            ->setAttribute('placeholder', $this->translator->translate('products.frontend.shop.checkout.fields.company_vat_id_placeholder'));

        if (!$payment) {
            // display terms and conditions if URL is configured
            $termsURL = $this->applicationConfig->get('shop_terms_and_conditions_url');
            if ($termsURL !== null && !empty(trim($termsURL))) {
                $toc = $form->addCheckbox('toc1', Html::el()->setHtml($this->translator->translate(
                    'products.frontend.shop.checkout.fields.toc',
                    ['link' => $termsURL]
                )));
                $toc->addConditionOn($action, Form::NOT_EQUAL, 'login')
                    ->addRule(Form::FILLED, $this->translator->translate('products.frontend.shop.checkout.fields.toc_required'));
                $toc->getLabelPrototype()->addAttributes(['class' => 'checkbox-inline']);
            }
        }

        $form->addSubmit('finish', $this->translator->translate('products.frontend.shop.checkout.fields.login'));
        $form->addProtection();

        $form->setDefaults($defaults);

        if ($payment) {
            $form->addHidden('payment_id', $payment->id);
            $form->onSuccess[] = [$this, 'formAdminSucceeded'];
        } else {
            $form->onSuccess[] = [$this, 'formSucceeded'];
        }

        return $form;
    }

    public function addPaymentGateway($code, $label)
    {
        $this->gateways[$code] = $label;
    }

    public function gatewayLabel($code)
    {
        if (!isset($this->gateways[$code])) {
            throw new \Exception('request for label of gateway not registered in checkout form: ' . $code);
        }
        return $this->gateways[$code];
    }

    public function formSucceeded($form, $values)
    {
        if ($values['action'] == 'login') {
            $this->user->setExpiration('14 days', false);
            try {
                $this->user->login(['username' => $values['user']['email'], 'password' => $values['user']['password']]);
                $this->user->setAuthorizator($this->authorizator);

                $cart = Json::decode($values['cart'], Json::FORCE_ARRAY);
                $products = $this->productsRepository->findByIds(array_keys($cart));
                $removeProducts = [];
                foreach ($products as $product) {
                    if ($product->unique_per_user && $this->paymentItemHelper->hasUniqueProduct($product, $this->user->getId())) {
                        $removeProducts[] = $product;
                    }
                }

                $this->onAuth->__invoke($this->user->getId());
                $this->onLogin->__invoke($removeProducts);
            } catch (AuthenticationException $e) {
                $form['user']['password']->addError($this->translator->translate('products.frontend.shop.checkout.warnings.unable_to_login_with_password'));
                $form['user']['password']->getControlPrototype()->addClass('error');
                return;
            }
        }

        if (!$this->user->isLoggedIn()) {
            $email = filter_input(INPUT_POST, 'user', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            try {
                $user = $this->userManager->addNewUser($email['email'], true, 'shop');
            } catch (InvalidEmailException $e) {
                $form['user']['email']->addError($this->translator->translate('products.frontend.shop.checkout.warnings.invalid_email'));
                return;
            }

            if (!$user) {
                $form['user']['email']->addError($this->translator->translate('products.frontend.shop.checkout.warnings.unable_to_create_user'));
                return;
            }
            $this->onAuth->__invoke($user->id);
        } else {
            $user = $this->userManager->loadUser($this->user);
        }

        $paymentGateway = $this->paymentGatewaysRepository->find($values['payment_gateway']);

        $amount = 0;

        // add products
        $cart = Json::decode($values['cart'], Json::FORCE_ARRAY);
        $products = $this->productsRepository->findByIds(array_keys($cart));
        foreach ($products as $product) {
            $amount += $product->price * $cart[$product->id];
        }

        // add postal fee
        $postalFee = $this->handlePostalFee($values);
        $postalFeeVat = null;
        if (!is_null($postalFee)) {
            $amount += $postalFee->amount;
        }

        // populate payment item container
        $paymentItemsContainer = new PaymentItemContainer();
        foreach ($products as $product) {
            $paymentItemsContainer->addItem(
                new ProductPaymentItem(
                    $product,
                    $cart[$product->id]
                )
            );
            if ($postalFeeVat === null || $product->vat > $postalFeeVat) {
                $postalFeeVat = $product->vat;
            }
        }
        if (Json::encode($this->cartFree) == $values['cart_free']) {
            $freeCart = Json::decode($values['cart_free'], Json::FORCE_ARRAY);
            $freeProducts = $this->productsRepository->findByIds(array_keys($freeCart));
            foreach ($freeProducts as $product) {
                $paymentItemsContainer->addItem(
                    (new ProductPaymentItem($product, $freeCart[$product->id]))
                        ->forceVat(0)
                        ->forcePrice(0)
                );
            }
        }
        if ($postalFee) {
            if ($postalFeeVat === null) {
                throw new \Exception("attempt to use uninitialized postal fee VAT (should have been calculated based on sold items)");
            }
            $postalFeeItem = new PostalFeePaymentItem(
                $postalFee,
                $postalFeeVat
            );
            $postalFeeItem->forceName(
                sprintf(
                    "%s - %s",
                    $this->translator->translate('products.frontend.orders.postal_fee'),
                    $postalFeeItem->name()
                )
            );
            $paymentItemsContainer->addItem($postalFeeItem);
        }

        $payment = $this->paymentsRepository->add(
            null,
            $paymentGateway,
            $user,
            $paymentItemsContainer,
            $this->request->getUrl()->getBaseUrl(),
            $amount
        );

        $shippingAddressId = $this->handleShippingAddress($user, $values);
        $licenceAddressId = $this->handleLicenceAddress($user, $values);
        $billingAddressId = $this->handleBillingAddress($user, $values);

        /** @var CheckoutFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('products.dataprovider.checkout_form', CheckoutFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            [$form, $values] = $provider->formSucceeded($form, $values, [
                'payment' => $payment,
            ]);
        }

        $this->ordersRepository->add($payment->id, $shippingAddressId, $licenceAddressId, $billingAddressId, $postalFee, $values['note']);

        $this->onSave->__invoke($payment);
    }

    public function formAdminSucceeded($form, $values)
    {
        $payment = $this->paymentsRepository->find($values['payment_id']);
        $user = $payment->user;

        $shippingAddressId = $this->handleShippingAddress($user, $values);
        $licenceAddressId = $this->handleLicenceAddress($user, $values);
        $billingAddressId = $this->handleBillingAddress($user, $values);
        $postalFee = $this->handlePostalFee($values);

        /** @var CheckoutFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('products.dataprovider.checkout_form', CheckoutFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            [$form, $values] = $provider->formSucceeded($form, $values, [
                'payment' => $payment,
            ]);
        }

        $this->ordersRepository->add($payment->id, $shippingAddressId, $licenceAddressId, $billingAddressId, $postalFee, $values['note']);
        $amount = 0;
        /** @var ActiveRow $paymentItem */
        foreach ($payment->related('payment_items') as $paymentItem) {
            $amount += $paymentItem->amount * $paymentItem->count;
        }
        if ($postalFee) {
            $amount += $postalFee->amount;
        }
        $this->paymentsRepository->update($payment, [
            'amount' => $amount,
        ]);

        $this->onSave->__invoke($payment);
    }

    private function handleShippingAddress($user, $values)
    {
        $shippingAddressId = null;
        if (isset($values['shipping_address'])) {
            $values['shipping_address']['phone_number'] = $values['contact']['phone_number'];
            $shippingAddress = $this->addressesRepository->findByAddress($values['shipping_address'], 'shop', $user->id);
            if (!$shippingAddress) {
                $shippingAddress = $this->addressesRepository->add(
                    $user,
                    'shop',
                    $values['shipping_address']['first_name'],
                    $values['shipping_address']['last_name'],
                    $values['shipping_address']['address'],
                    $values['shipping_address']['number'],
                    $values['shipping_address']['city'],
                    $values['shipping_address']['zip'],
                    $values['shipping_address']['country_id'],
                    $values['shipping_address']['phone_number']
                );
            }
            $this->addressesRepository->update($shippingAddress, []);
            $shippingAddressId = $shippingAddress->id;
        }
        return $shippingAddressId;
    }

    private function handleLicenceAddress($user, $values)
    {
        $licenceAddressId = null;
        if (isset($values['licence_address'])) {
            $values['licence_address']['phone_number'] = $values['contact']['phone_number'];
            $licenceAddress = $this->addressesRepository->findByAddress($values['licence_address'], 'licence', $user->id);
            if (!$licenceAddress) {
                $licenceAddress = $this->addressesRepository->add(
                    $user,
                    'licence',
                    $values['licence_address']['first_name'],
                    $values['licence_address']['last_name'],
                    null,
                    null,
                    null,
                    null,
                    null,
                    $values['licence_address']['phone_number']
                );
            }
            $this->addressesRepository->update($licenceAddress, []);
            $licenceAddressId = $licenceAddress->id;
        }
        return $licenceAddressId;
    }

    private function handleBillingAddress($user, $values)
    {
        $billingAddressId = null;
        if ($values['invoice']['add_invoice']) {
            $changeRequest = null;
            $billingAddress = $this->addressesRepository->address($user, 'invoice');

            if (isset($values['shipping_address']) && isset($values['invoice']['same_shipping']) && $values['invoice']['same_shipping']) {
                $changeRequest = $this->addressChangeRequestsRepository->add(
                    $user,
                    $billingAddress,
                    $values['shipping_address']['first_name'],
                    $values['shipping_address']['last_name'],
                    null,
                    $values['shipping_address']['address'],
                    $values['shipping_address']['number'],
                    $values['shipping_address']['city'],
                    $values['shipping_address']['zip'],
                    $values['shipping_address']['country_id'],
                    null,
                    null,
                    null,
                    $values['shipping_address']['phone_number'],
                    'invoice'
                );
                if ($changeRequest) {
                    $billingAddress = $this->addressChangeRequestsRepository->acceptRequest($changeRequest);
                }
            } else {
                $values['billing_address']['phone_number'] = $values['contact']['phone_number'];
                $changeRequest = $this->addressChangeRequestsRepository->add(
                    $user,
                    $billingAddress,
                    null,
                    null,
                    $values['billing_address']['company_name'],
                    $values['billing_address']['address'],
                    $values['billing_address']['number'],
                    $values['billing_address']['city'],
                    $values['billing_address']['zip'],
                    $values['billing_address']['country_id'],
                    $values['billing_address']['company_id'],
                    $values['billing_address']['company_tax_id'],
                    $values['billing_address']['company_vat_id'],
                    $values['billing_address']['phone_number'],
                    'invoice'
                );
                if ($changeRequest) {
                    $billingAddress = $this->addressChangeRequestsRepository->acceptRequest($changeRequest);
                }
            }

            $this->usersRepository->update($user, ['invoice' => true]);
            $this->addressesRepository->update($billingAddress, []);
            $billingAddressId = $billingAddress->id;
        }
        return $billingAddressId;
    }

    private function handlePostalFee($values)
    {
        $postalFee = null;
        if (!empty($values['postal_fee'])) {
            $postalFee = $this->postalFeesRepository->find($values['postal_fee']);
        }
        return $postalFee;
    }
}
