<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ProductsModule\Forms\TagsFormFactory;
use Crm\ProductsModule\Repository\TagsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;

class TagsAdminPresenter extends AdminPresenter
{
    /** @var Request @inject */
    public $request;

    /** @var TagsRepository @inject */
    public $tagsRepository;

    /** @var TagsFormFactory @inject */
    public $tagsFormFactory;

    public function renderDefault()
    {
        $this->template->tags = $this->tagsRepository->all();
    }

    public function renderNew()
    {
    }

    public function renderEdit($id)
    {
        $tag = $this->tagsRepository->find($id);
        if (!$tag) {
            $this->flashMessage($this->translator->translate('products.admin.products.messages.tag_not_found'));
            $this->redirect('default');
        }
        $this->template->tag = $tag;
    }

    protected function createComponentTagsForm()
    {
        $id = $this->getParameter('id');
        $form = $this->tagsFormFactory->create($id);

        $this->tagsFormFactory->onSave = function (ActiveRow $tag) {
            $this->flashMessage($this->translator->translate('products.admin.tags.messages.tag_created'));
            $this->redirect('Default');
        };
        $this->tagsFormFactory->onUpdate = function (ActiveRow $tag) {
            $this->flashMessage($this->translator->translate('products.admin.tags.messages.tag_updated'));
            $this->redirect('Default');
        };

        return $form;
    }
}
