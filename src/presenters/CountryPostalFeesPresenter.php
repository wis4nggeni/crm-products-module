<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ProductsModule\PostalFeeCondition\PostalFeeConditionInterface;
use Crm\ProductsModule\PostalFeeCondition\PostalFeeService;
use Crm\ProductsModule\Repository\CountryPostalFeesRepository;
use Crm\ProductsModule\Repository\CountryPostalFeeConditionsRepository;
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

    private $countryPostalFeeConditionsRepository;

    private $postalFeeService;

    public function __construct(
        PostalFeesRepository $postalFeesRepository,
        CountryPostalFeesRepository $countryPostalFeesRepository,
        CountriesRepository $countriesRepository,
        PriceHelper $priceHelper,
        CountryPostalFeeConditionsRepository $countryPostalFeeConditionsRepository,
        PostalFeeService $postalFeeService
    ) {
        parent::__construct();

        $this->postalFeesRepository = $postalFeesRepository;
        $this->countryPostalFeesRepository = $countryPostalFeesRepository;
        $this->countriesRepository = $countriesRepository;
        $this->priceHelper = $priceHelper;
        $this->countryPostalFeeConditionsRepository = $countryPostalFeeConditionsRepository;
        $this->postalFeeService = $postalFeeService;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $countries = $this->countriesRepository->all();
        $this->template->countries = $countries;
        $this->template->postalFeeService = $this->postalFeeService;
    }

    public function createComponentAddForm(): Form
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->getElementPrototype()->addAttributes(['class' => 'ajax']);

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

        $form->addText('sorting', 'products.data.country_postal_fees.fields.sorting')
            ->addRule(Form::INTEGER, 'products.admin.country_postal_fees.default.submit')
            ->addRule(Form::FILLED, 'products.admin.country_postal_fees.default.sorting_required')
            ->setDefaultValue(100);
        $form->addCheckbox('active', 'products.data.country_postal_fees.fields.active')
            ->setDefaultValue(true);
        $form->addCheckbox('default', 'products.data.country_postal_fees.fields.default');

        $registeredConditions = $this->postalFeeService->getRegisteredConditions();
        $conditions = array_map(function (PostalFeeConditionInterface $condition) {
            return $condition->getLabel();
        }, $registeredConditions);

        $conditionSelect = $form->addSelect(
            'condition',
            'products.data.country_postal_fees.fields.condition',
            $conditions,
        )->setPrompt('----');

        $conditionValueInput = $form->addText('condition_value', 'products.data.country_postal_fees.fields.condition_value')
            ->addConditionOn($conditionSelect, Form::FILLED)
            ->addRule(Form::FILLED, 'products.admin.country_postal_fees.default.condition_value_required');

        foreach ($registeredConditions as $key => $condition) {
            foreach ($condition->getValidationRules() as $validationRule) {
                $conditionValueInput->addConditionOn($conditionSelect, Form::EQUAL, $key)
                    ->addRule(...$validationRule);
            }
        }

        $form->addSubmit('submit', 'products.admin.country_postal_fees.default.submit');

        $form->onSuccess[] = function (Form $form, $values) {
            $countryPostalFeeRow = $this->countryPostalFeesRepository
                ->getByCountryAndPostalFee($values['country_id'], $values['postal_fee_id']);

            if ($countryPostalFeeRow) {
                if (empty($values['condition'])) {
                    $this->flashMessage($this->translator->translate('products.admin.country_postal_fees.default.error_already_exists'), 'error');
                    $this->redirect('default');
                }

                $relatedConditions = $countryPostalFeeRow->related('country_postal_fee_conditions', 'country_postal_fee_id');
                if (!empty($values['condition']) && $relatedConditions->where('code', $values['condition'])->count('*') > 0) {
                    $this->flashMessage($this->translator->translate('products.admin.country_postal_fees.default.error_already_exists'), 'error');
                    $this->redirect('default');
                }
            }

            $databaseContext = $this->countryPostalFeesRepository->getDatabase();
            $databaseContext->beginTransaction();

            $countryPostalFeeRow = $this->countryPostalFeesRepository->add(
                $values['country_id'],
                $values['postal_fee_id'],
                $values['sorting'],
                $values['default'],
                $values['active']
            );

            try {
                if ($values['condition']) {
                    $this->countryPostalFeeConditionsRepository->add(
                        $countryPostalFeeRow,
                        $values['condition'],
                        $values['condition_value']
                    );
                }
            } catch (\Exception $exception) {
                $databaseContext->rollBack();
            }

            $databaseContext->commit();

            $this->flashMessage($this->translator->translate('products.admin.country_postal_fees.default.successfully_added'));
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
        $countryPostalFee->related('country_postal_fee_conditions', 'country_postal_fee_id')->delete();
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
