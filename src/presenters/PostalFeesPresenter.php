<?php

namespace Crm\ProductsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ProductsModule\Repository\PostalFeesRepository;

class PostalFeesPresenter extends AdminPresenter
{
    private $postalFeesRepository;

    public function __construct(PostalFeesRepository $postalFeesRepository)
    {
        $this->postalFeesRepository = $postalFeesRepository;
    }

    public function renderDefault()
    {
        $this->template->postalFees = $this->postalFeesRepository->all()->order('id');
    }
}
