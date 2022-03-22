<?php

namespace Crm\ProductsModule\Forms;

use Crm\ProductsModule\Repository\ProductsRepository;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

class SortShopProductsFormFactory
{
    public ProductsRepository $productsRepository;

    public Translator $translator;

    public $onSave;

    public function __construct(
        ProductsRepository $productsRepository,
        Translator $translator
    ) {
        $this->productsRepository = $productsRepository;
        $this->translator = $translator;
    }

    public function create()
    {
        $form = new Form;
        $form->addProtection();

        $form->addSubmit('submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form)
    {
        $productIds = $form->getHttpData($form::DATA_TEXT, 'products[]');

        $sorting = $this->productsRepository->getTable()->where('id', $productIds)->fetchPairs('id', 'sorting');
        $sortingValues = array_values($sorting);
        asort($sortingValues, SORT_NUMERIC);
        $sortingValues = array_values($sortingValues);

        $this->productsRepository->resortProducts(array_combine($productIds, $sortingValues));
        $this->onSave->__invoke();
    }
}
