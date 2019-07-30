<?php

namespace Crm\ProductsModule\Forms;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ProductsModule\Builder\ProductBuilder;
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

        $form->addText('name', $this->translator->translate('products.data.products.fields.name'))
            ->setRequired($this->translator->translate('products.data.products.errors.name'))
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.name'));

        $form->addText('code', $this->translator->translate('products.data.products.fields.code'))
            ->setRequired($this->translator->translate('products.data.products.errors.code'))
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.code'));

        $form->addText('user_label', $this->translator->translate('products.data.products.fields.user_label'))
            ->setOption('description', $this->translator->translate('products.data.products.descriptions.user_label'))
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.user_label'));

        $form->addText('price', $this->translator->translate('products.data.products.fields.price'))
            ->setRequired($this->translator->translate('products.data.products.errors.price_with_vat'))
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.price'));

        $form->addText('catalog_price', $this->translator->translate('products.data.products.fields.catalog_price'))
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.catalog_price'));

        $form->addInteger('vat', $this->translator->translate('products.data.products.fields.vat'));

        $form->addText('stock', $this->translator->translate('products.data.products.fields.stock'));

        $shop = $form->addCheckbox('shop', $this->translator->translate('products.data.products.fields.shop'));

        $sorting = $form->addSelect('sorting', $this->translator->translate('products.data.products.fields.sorting'), $sortingPairs)
            ->setOption('id', 'sorting')
            ->setPrompt($this->translator->translate('products.data.products.placeholder.sorting'));

        $tagPairs = $this->tagsRepository->all()->fetchPairs('id', 'code');
        $tags = $form->addMultiSelect('tags', $this->translator->translate('products.data.products.fields.tags'), $tagPairs)->setOption('id', 'tags');
        $tags->getControlPrototype()->addAttributes(['class' => 'select2']);

        $description = $form->addTextArea('description', $this->translator->translate('products.data.products.fields.description'))
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.description'))
            ->setAttribute('rows', 10)
            ->setOption('id', 'description');

        $image = $form->addText('image_url', $this->translator->translate('products.data.products.fields.image_url'))
            ->setOption('description', $this->translator->translate('products.data.products.descriptions.image_url'))
            ->setOption('id', 'image')
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.image_url'));

        $ogImage = $form->addText('og_image_url', $this->translator->translate('products.data.products.fields.og_image_url'))
            ->setOption('id', 'ogImage')
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.og_image_url'));

        $images = $form->addTextArea('images', $this->translator->translate('products.data.products.fields.images'))
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.images'))
            ->setOption('description', $this->translator->translate('products.data.products.descriptions.images'))
            ->setOption('id', 'images')
            ->setAttribute('rows', 5);

        $ean = $form->addText('ean', $this->translator->translate('products.data.products.fields.ean'))
            ->setOption('id', 'ean')
            ->setAttribute('placeholder', $this->translator->translate('products.data.products.placeholder.ean'));

        $distributionCenters = $this->distributionCentersRepository->all()->fetchPairs('code', 'name');
        $distributionCenter = $form->addSelect('distribution_center', $this->translator->translate('products.data.products.fields.distribution_center'), $distributionCenters)
            ->setOption('id', 'distributionCenter')
            ->setPrompt($this->translator->translate('products.data.products.placeholder.distribution_center'));

        $visible = $form->addCheckbox('visible', $this->translator->translate('products.data.products.fields.visible'))->setOption('id', 'visible');
        $unique = $form->addCheckbox('unique_per_user', $this->translator->translate('products.data.products.fields.unique_per_user'))->setOption('id', 'unique');
        $delivery = $form->addCheckbox('has_delivery', $this->translator->translate('products.data.products.fields.has_delivery'))->setOption('id', 'delivery');

        $image->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, $this->translator->translate('products.data.products.errors.image_url'));

        $ogImage->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, $this->translator->translate('products.data.products.errors.og_image_url'));

        $description->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, $this->translator->translate('products.data.products.errors.description'));

        $ean->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, $this->translator->translate('products.data.products.errors.ean'))
            ->addRule(Form::LENGTH, $this->translator->translate('products.data.products.errors.ean13'), 13);

        $distributionCenter->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, $this->translator->translate('products.data.products.errors.distribution_center'));

        $distributionCenter->addConditionOn($shop, Form::EQUAL, true)
            ->addRule(Form::FILLED, $this->translator->translate('products.data.products.errors.distribution_center'));

        $templates = $this->productTemplatesRepository->all();
        $templateId = $form->addSelect('product_template_id', $this->translator->translate('products.data.products.fields.template_id'), $templates->fetchPairs('id', 'name'))
            ->setOption('id', 'templateId')
            ->setPrompt('No Template');

        $shop->addCondition(Form::EQUAL, true)
            ->toggle($sorting->getOption('id'))
            ->toggle($tags->getOption('id'))
            ->toggle($description->getOption('id'))
            ->toggle($ean->getOption('id'))
            ->toggle($image->getOption('id'))
            ->toggle($images->getOption('id'))
            ->toggle($ogImage->getOption('id'))
            ->toggle($distributionCenter->getOption('id'))
            ->toggle($visible->getOption('id'))
            ->toggle($unique->getOption('id'))
            ->toggle($delivery->getOption('id'))
            ->toggle($templateId->getOption('id'));

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

        $bundle = $form->addCheckbox('bundle', $this->translator->translate('products.data.products.fields.bundle'));
        $bundleItems = $form->addMultiSelect(
            'bundle_items',
            $this->translator->translate('products.data.products.fields.bundle_items'),
            $this->productsRepository->getTable()->where([
                'bundle' => false,
            ])->fetchPairs('id', 'name')
        )->setOption('id', 'bundleItems');

        $bundleItems->getControlPrototype()->addAttributes(['class' => 'select2']);
        $bundle->addCondition(Form::EQUAL, true)
            ->toggle($bundleItems->getOption('id'));

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        if ($productId) {
            $form->addHidden('product_id', $productId);
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
