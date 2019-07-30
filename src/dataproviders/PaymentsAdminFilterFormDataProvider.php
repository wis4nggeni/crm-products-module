<?php

namespace Crm\ProductsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\PaymentsModule\DataProvider\AdminFilterFormDataProviderInterface;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Crm\ProductsModule\Repository\ProductsRepository;
use Nette\Application\Request;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;

class PaymentsAdminFilterFormDataProvider implements AdminFilterFormDataProviderInterface
{
    private $productsRepository;

    public function __construct(ProductsRepository $productsRepository)
    {
        $this->productsRepository = $productsRepository;
    }

    /**
     * @param array $params
     * @throws DataProviderException
     */
    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['form']));
        }

        if (!isset($params['request'])) {
            throw new DataProviderException('missing [request] within data provider params');
        }
        if (!($params['request'] instanceof Request)) {
            throw new DataProviderException('invalid type of provided request: ' . get_class($params['request']));
        }

        $form = $params['form'];
        $request = $params['request'];

        $products = $this->productsRepository->getTable()->fetchPairs('id', 'name');
        $form->addMultiSelect('products', 'Produkty', $products)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->setDefaults([
            'products' => $request->getParameter('products'),
        ]);

        return $form;
    }

    public function filter(Selection $selection, Request $request): Selection
    {
        if ($request->getParameter('products')) {
            $selection
                ->where(':payment_items.type', ProductPaymentItem::TYPE)
                ->where(':payment_items.product_id', $request->getParameter('products'));
        }
        return $selection;
    }
}
