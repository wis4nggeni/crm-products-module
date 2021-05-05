<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ProductsModule\Repository\CountryPostalFeesRepository;
use Crm\ProductsModule\Repository\PostalFeesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapRenderer;

class CountryPostalFeesPresenter extends AdminPresenter
{
    private $postalFeesRepository;

    private $countryPostalFeesRepository;

    private $countriesRepository;

    private $priceHelper;

    public function __construct(
        PostalFeesRepository $postalFeesRepository,
        CountryPostalFeesRepository $countryPostalFeesRepository,
        CountriesRepository $countriesRepository,
        PriceHelper $priceHelper
    ) {
        parent::__construct();

        $this->postalFeesRepository = $postalFeesRepository;
        $this->countryPostalFeesRepository = $countryPostalFeesRepository;
        $this->countriesRepository = $countriesRepository;
        $this->priceHelper = $priceHelper;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $countries = $this->countriesRepository->all();
        $this->template->countries = $countries;
    }

    public function createComponentAddForm(): Form
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $countries = $form->addSelect(
            'country_id',
            'products.data.country_postal_fees.fields.country_id',
            $this->countriesRepository->all()->fetchPairs('id', 'name')
        );
        $countries->getControlPrototype()->addAttributes(['class' => 'select2']);

        $postalFees = $form->addSelect(
            'postal_fee_id',
            'products.data.country_postal_fees.fields.postal_fee_id',
            $this->getPostalFees()
        );
        $postalFees->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addInteger('sorting', 'products.data.country_postal_fees.fields.sorting')
            ->setDefaultValue(100);
        $form->addCheckbox('active', 'products.data.country_postal_fees.fields.active')
            ->setDefaultValue(true);
        $form->addCheckbox('default', 'products.data.country_postal_fees.fields.default');
        $form->addSubmit('submit', 'products.admin.country_postal_fees.default.submit');

        $form->onSuccess[] = function (Form $form, $values) {
            if ($this->countryPostalFeesRepository->exists($values['country_id'], $values['postal_fee_id'])) {
                $this->flashMessage('products.admin.country_postal_fees.default.error_already_exists', 'error');
                $this->redirect('default');
            }
            $this->countryPostalFeesRepository->add(
                $values['country_id'],
                $values['postal_fee_id'],
                $values['sorting'],
                $values['default'],
                $values['active']
            );
            $this->flashMessage('products.admin.country_postal_fees.default.successfully_added');
            $this->redirect('default');
        };
        return $form;
    }

    private function getPostalFees(): array
    {
        $postalFees = $this->postalFeesRepository->all()->order('id');
        $result = [];
        foreach ($postalFees as $postalFee) {
            $result[$postalFee->id] = sprintf(
                '%s / %s',
                $postalFee->title,
                $this->priceHelper->getFormattedPrice($postalFee->amount)
            );
        }
        return $result;
    }

    /**
     * @admin-access-level write
     */
    public function handleDelete($id)
    {
        $countryPostalFee = $this->countryPostalFeesRepository->find($id);
        $this->countryPostalFeesRepository->delete($countryPostalFee);
        $this->flashMessage($this->translator->translate('products.admin.country_postal_fees.default.successfully_deleted'));
        $this->redirect('default');
    }

    /**
     * @admin-access-level write
     */
    public function handleInactive($id)
    {
        $countryPostalFee = $this->countryPostalFeesRepository->find($id);
        $this->countryPostalFeesRepository->setInactive($countryPostalFee);
        $this->redirect('default');
    }

    /**
     * @admin-access-level write
     */
    public function handleActive($id)
    {
        $countryPostalFee = $this->countryPostalFeesRepository->find($id);
        $this->countryPostalFeesRepository->setActive($countryPostalFee);
        $this->redirect('default');
    }
}
