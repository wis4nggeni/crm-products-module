<?php

namespace Crm\ProductsModule\Forms;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ProductsModule\Builder\ProductBuilder;
use Crm\ProductsModule\DataProvider\ProductsFormDataProviderInterface;
use Crm\ProductsModule\Events\ProductSaveEvent;
use Crm\ProductsModule\ProductsCache;
use Crm\ProductsModule\Repository\DistributionCentersRepository;
use Crm\ProductsModule\Repository\ProductBundlesRepository;
use Crm\ProductsModule\Repository\ProductPropertiesRepository;
use Crm\ProductsModule\Repository\ProductsRepository;
use Crm\ProductsModule\Repository\ProductTagsRepository;
use Crm\ProductsModule\Repository\ProductTemplatePropertiesRepository;
use Crm\ProductsModule\Repository\ProductTemplatesRepository;
use Crm\ProductsModule\Repository\TagsRepository;
use Kdyby\Translation\Translator;
use League\Event\Emitter;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ProductsFormFactory
{
    private $productsRepository;
    private $productPropertiesRepository;
    private $productTemplatesRepository;
    private $productTemplatePropertiesRepository;
    private $productBundlesRepository;
    private $tagsRepository;
    private $productTagsRepository;
    private $distributionCentersRepository;
    private $productsCache;
    private $dataProviderManager;

    private $translator;

    private $productBuilder;

    private $emitter;

    private $config;

    public $onSave;

    public $onUpdate;

    public function __construct(
        ProductsRepository $productsRepository,
        ProductPropertiesRepository $productPropertiesRepository,
        ProductTemplatesRepository $productTemplatesRepository,
        ProductTemplatePropertiesRepository $productTemplatePropertiesRepository,
        ProductBundlesRepository $productBundlesRepository,
        TagsRepository $tagsRepository,
        ProductTagsRepository $productTagsRepository,
        DistributionCentersRepository $distributionCentersRepository,
        ProductsCache $productsCache,
        DataProviderManager $dataProviderManager,
        ProductBuilder $productBuilder,
        Translator $translator,
        Emitter $emitter,
        ApplicationConfig $applicationConfig
    ) {
        $this->productsRepository = $productsRepository;
        $this->productPropertiesRepository = $productPropertiesRepository;
        $this->productTemplatesRepository = $productTemplatesRepository;
        $this->productTemplatePropertiesRepository = $productTemplatePropertiesRepository;
        $this->productBundlesRepository = $productBundlesRepository;
        $this->tagsRepository = $tagsRepository;
        $this->productTagsRepository = $productTagsRepository;
        $this->distributionCentersRepository = $distributionCentersRepository;
        $this->productsCache = $productsCache;
        $this->dataProviderManager = $dataProviderManager;
        $this->productBuilder = $productBuilder;
        $this->translator = $translator;
        $this->emitter = $emitter;
        $this->config = $applicationConfig;
    }

    /**
     * @return Form
     */
    public function create($productId)
    {
        $defaults = [];
        $products = $this->productsRepository->getShopProducts(false, false);
        if (isset($productId)) {
            $products->where('id != ?', $productId);
        }
        $sortingPairs = $products->fetchPairs('sorting', 'name');

        if (isset($productId)) {
            $product = $this->productsRepository->find($productId);
            $defaults = $product->toArray();

            if (isset($defaults['sorting']) && $defaults['sorting'] > 1) {
                $defaultSorting = null;
                foreach ($sortingPairs as $sorting => $_) {
                    if (is_numeric($sorting) && $defaults['sorting'] < $sorting) {
                        break;
                    }
                    $defaultSorting = $sorting;
                }
                $defaults['sorting'] = $defaultSorting;
                reset($sortingPairs);
            } else {
                $defaults['sorting'] = null;
            }

            if ($product->product_template_id) {
                foreach ($product->related('product_properties') as $property) {
                    $defaults['template_properties_' . $product->product_template_id][$property->product_template_property_id] = $property->value;
                }
            }

            /** @var ActiveRow $pair */
            foreach ($product->related('product_bundles', 'bundle_id') as $pair) {
                $defaults['bundle_items'][] = $pair->item_id;
            }

            foreach ($product->related('product_tags') as $pair) {
                $defaults['tags'][] = $pair->tag_id;
            }
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addGroup();

        $form->addText('name', 'products.data.products.fields.name')
            ->setRequired('products.data.products.errors.name')
            ->setAttribute('placeholder', 'products.data.products.placeholder.name');

        $form->addText('code', 'products.data.products.fields.code')
            ->setRequired('products.data.products.errors.code')
            ->setAttribute('placeholder', 'products.data.products.placeholder.code');

        $form->addText('user_label', 'products.data.products.fields.user_label')
            ->setOption('description', 'products.data.products.descriptions.user_label')
            ->setAttribute('placeholder', 'products.data.products.placeholder.user_label');

        $form->addText('price', 'products.data.products.fields.price')
            ->setRequired('products.data.products.errors.price_with_vat')
            ->setAttribute('placeholder', 'products.data.products.placeholder.price');

        $form->addText('catalog_price', 'products.data.products.fields.catalog_price')
            ->setAttribute('placeholder', 'products.data.products.placeholder.catalog_price');

        $form->addInteger('vat', 'products.data.products.fields.vat')
            ->setRequired('products.data.products.errors.vat');

        $form->addText('stock', 'products.data.products.fields.stock');

        $bundle = $form->addCheckbox('bundle', 'products.data.products.fields.bundle');
        $bundleItems = $form->addMultiSelect(
            'bundle_items',
            'products.data.products.fields.bundle_items',
            $this->productsRepository->getTable()->where([
                'bundle' => false,
            ])->fetchPairs('id', 'name')
        )->setOption('id', 'bundleItems');

        $bundleItems->getControlPrototype()->addAttributes(['class' => 'select2']);
        $bundle->addCondition(Form::EQUAL, true)
            ->toggle($bundleItems->getOption('id'));

        // SHOP SPECIFIC SETTINGS

        $shop = $form->addCheckbox('shop', 'products.data.products.fields.shop');

        $form->addGroup($this->translator->translate('products.data.products.fields.shop_settings'))
            ->setOption('container', Html::el('div', ['id' => 'shop-container']));

        $sorting = $form->addSelect('sorting', 'products.data.products.fields.sorting', $sortingPairs)
            ->setOption('id', 'sorting')
            ->setPrompt('products.data.products.placeholder.sorting');

        $tagPairs = $this->tagsRepository->all()->fetchPairs('id', 'code');
        $tags = $form->addMultiSelect('tags', 'products.data.products.fields.tags', $tagPairs)->setOption('id', 'tags');
        $tags->getControlPrototype()->addAttributes(['class' => 'select2']);

        $description = $form->addTextArea('description', 'products.data.products.fields.description')
            ->setAttribute('placeholder', 'products.data.products.placeholder.description')
            ->setAttribute('rows', 10)
            ->setAttribute('data-html-editor', [])
            ->setOption('id', 'description');

        $image = $form->addText('image_url', 'products.data.products.fields.image_url')
            ->setOption('description', 'products.data.products.descriptions.image_url')
            ->setOption('id', 'image')
            ->setAttribute('placeholder', 'products.data.products.placeholder.image_url');

        $ogImage = $form->addText('og_image_url', 'products.data.products.fields.og_image_url')
            ->setOption('id', 'ogImage')
            ->setAttribute('placeholder', 'products.data.products.placeholder.og_image_url');

        $images = $form->addTextArea('images', 'products.data.products.fields.images')
            ->setAttribute('placeholder', 'products.data.products.placeholder.images')
            ->setOption('description', 'products.data.products.descriptions.images')
            ->setOption('id', 'images')
            ->setAttribute('rows', 5);

        $ean = $form->addText('ean', 'products.data.products.fields.ean')
            ->setOption('id', 'ean')
            ->setAttribute('placeholder', 'products.data.products.placeholder.ean');

        $distributionCenters = $this->distributionCentersRepository->all()->fetchPairs('code', 'name');
        $distributionCenter = $form->addSelect('distribution_center', 'products.data.products.fields.distribution_center', $distributionCenters)
            ->setOption('id', 'distributionCenter')
            ->setPrompt('--');

        $visible = $form->addCheckbox('visible', 'products.data.products.fields.visible')->setOption('id', 'visible');
        $unique = $form->addCheckbox('unique_per_user', 'products.data.products.fields.unique_per_user')->setOption('id', 'unique');
        $delivery = $form->addCheckbox('has_delivery', 'products.data.products.fields.has_delivery')->setOption('id', 'delivery');

        $image->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, 'products.data.products.errors.image_url');

        $ogImage->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, 'products.data.products.errors.og_image_url');

        $description->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, 'products.data.products.errors.description');

        $ean->addCondition(Form::FILLED)
            ->addRule(Form::LENGTH, 'products.data.products.errors.ean13', 13);

        $templates = $this->productTemplatesRepository->all();
        $templateId = $form->addSelect('product_template_id', 'products.data.products.fields.template_id', $templates->fetchPairs('id', 'name'))
            ->setOption('id', 'templateId')
            ->setPrompt('No Template');

        $shop->addCondition(Form::EQUAL, true)
            ->toggle('shop-container');

        /**
         * @TODO Refactor this to load template part form by AJAX
         */
        foreach ($templates as $template) {
            $templateContainer = $form->addContainer('template_properties_' . $template->id);

            $templateProperties = $this->productTemplatePropertiesRepository->findByTemplate($template);
            foreach ($templateProperties as $templateProperty) {
                $input = $templateContainer->addText($templateProperty->id, $templateProperty->title);
                $input->setOption("id", "template_property_{$templateProperty->id}");
                if ($templateProperty->required) {
                    $input->addConditionOn($templateId, Form::EQUAL, $template->id)
                        ->addRule(Form::FILLED, sprintf($this->translator->trans('products.data.products.errors.template_property'), $templateProperty->title));
                }
                if ($templateProperty->hint) {
                    $input->setHtmlAttribute('placeholder', $templateProperty->hint);
                }

                $templateId->addCondition(Form::EQUAL, $template->id)
                    ->toggle("template_property_{$templateProperty->id}");
            }
        }

        // reset group to default
        $form->setCurrentGroup();

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        if ($productId) {
            $form->addHidden('product_id', $productId);
        }

        /** @var ProductsFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('products.dataprovider.product_form', ProductsFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form]);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    /**
     * @param $form
     * @param $values
     */
    public function formSucceeded($form, $values)
    {
        $values['sorting'] = (int)$values['sorting'] + 1;

        $bundleItems = [];
        if ($values['bundle']) {
            $bundleItems = $values['bundle_items'];
        }
        unset($values['bundle_items']);

        $tags = [];
        if ($values['tags']) {
            $tags = $values['tags'];
        }
        unset($values['tags']);

        $productProperties = [];
        if ($values['shop']) {
            if (!empty($values['product_template_id'])) {
                $productProperties = $values['template_properties_' . $values['product_template_id']];
            }
        } else {
            unset($values['product_template_id']);
            unset($values['description']);
            unset($values['image_url']);
            unset($values['ean']);
        }

        $templates = $this->productTemplatesRepository->all();
        foreach ($templates as $template) {
            unset($values['template_properties_' . $template->id]);
        }

        if (isset($values['product_id'])) {
            $productId = $values['product_id'];
            unset($values['product_id']);

            $product = $this->productsRepository->find($productId);

            if ($values['sorting'] > $product->sorting) {
                $values['sorting'] = $values['sorting'] - 1;
            }
            $this->productsRepository->updateSorting($values['sorting'], $product->sorting);

            $this->productsRepository->update($product, $values);
            $this->productsCache->add($productId, $values['code']);
            $this->productBundlesRepository->setBundleItems($product, $bundleItems);
            $this->productPropertiesRepository->setProductProperties($product, $productProperties);
            $this->productTagsRepository->setProductTags($product, $tags);

            $this->emitter->emit(new ProductSaveEvent($product->id));
            $this->onUpdate->__invoke($product);

            return;
        }

        $product = $this->productBuilder->createNew()
            ->fromArray((array)$values)
            ->setBundleItems($bundleItems)
            ->setTemplateProperties($productProperties)
            ->setProductsTags($tags)
            ->save();

        if (!$product) {
            $form['name']->addError(implode("\n", $this->productBuilder->getErrors()));
            return;
        }

        $this->productsCache->add($product->id, $product->code);
        $this->emitter->emit(new ProductSaveEvent($product->id));
        $this->onSave->__invoke($product);
    }
}
