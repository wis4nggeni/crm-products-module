<?php

namespace Crm\ProductsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ProductsModule\Repository\TagsRepository;
use Crm\ProductsModule\TagsCache;
use Nette\Application\UI\Form;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use Tomaj\Form\Renderer\BootstrapRenderer;

class TagsFormFactory
{
    private $tagsRepository;

    private $translator;

    public $onSave;

    public $onUpdate;

    private $tagsCache;

    public function __construct(
        TagsCache $tagsCache,
        TagsRepository $tagsRepository,
        Translator $translator
    ) {
        $this->tagsRepository = $tagsRepository;
        $this->translator = $translator;
        $this->tagsCache = $tagsCache;
    }

    /**
     * @return Form
     */
    public function create($tagId)
    {
        $defaults = [];
        if (isset($tagId)) {
            $tag = $this->tagsRepository->find($tagId);
            $defaults = $tag->toArray();
            if (isset($defaults['sorting']) && $defaults['sorting'] > 1) {
                $defaults['sorting'] = $defaults['sorting'] - 1; // select element after which current tag is displayed
            } else {
                $defaults['sorting'] = null;
            }
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addText('name', 'products.data.tags.fields.name')
            ->setRequired('products.data.tags.errors.name')
            ->setHtmlAttribute('placeholder', 'products.data.tags.placeholder.name');

        $form->addText('code', 'products.data.tags.fields.code')
            ->setOption('description', 'products.data.tags.descriptions.code')
            ->setHtmlAttribute('placeholder', 'products.data.tags.placeholder.code')
            ->setDisabled(isset($tagId));

        $form->addText('icon', 'products.data.tags.fields.icon')
            ->setRequired('products.data.tags.errors.icon')
            ->setOption('description', Html::el('a href="https://fontawesome.io/icons/"', $this->translator->translate('products.data.tags.descriptions.icon')))
            ->setHtmlAttribute('placeholder', 'products.data.tags.placeholder.icon');

        $tagPairsQuery = $this->tagsRepository->all()->where('sorting IS NOT NULL');
        if ($tagId) {
            $tagPairsQuery->where('id != ?', $tagId);
        }
        $tagPairs = $tagPairsQuery->fetchPairs('sorting', 'name');
        $form->addSelect('sorting', 'products.data.tags.fields.sorting', $tagPairs)
            ->setPrompt('products.data.tags.placeholder.sorting');

        $form->addCheckbox('visible', 'products.data.tags.fields.visible');

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        if ($tagId) {
            $form->addHidden('id', $tagId);
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
        $values['sorting'] = (int)$values['sorting'] + 1; // sort after the selected element

        if (isset($values['id'])) {
            $tag = $this->tagsRepository->find($values['id']);

            if ($tag->sorting && $values['sorting'] > $tag->sorting) {
                $values['sorting'] = $values['sorting'] - 1;
            }
            $this->tagsRepository->updateSorting($values['sorting'], $tag->sorting);

            $this->tagsRepository->update($tag, $values);
            $this->onUpdate->__invoke($tag);
        } else {
            $code = Strings::webalize($values['code']);
            $tag = $this->tagsRepository->add($code, $values['name'], $values['icon'], $values['visible']);
            $this->tagsCache->add($tag->id, $code);
            $this->onSave->__invoke($tag);
        }
    }
}
