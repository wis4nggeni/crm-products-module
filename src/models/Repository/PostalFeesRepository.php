<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class PostalFeesRepository extends Repository
{
    protected $tableName = 'postal_fees';

    public function getByCountry($countryId)
    {
        return $this->getTable()->where([':country_postal_fees.country_id' => $countryId])->order('sorting');
    }

    public function getDefaultByCountry($countryId)
    {
        return $this->getByCountry($countryId)->where(['default' => true]);
    }

    public function add($code, $title, $amount)
    {
        return $this->insert([
            'code' => $code,
            'title' => $title,
            'amount' => $amount,
        ]);
    }

    public function exists($code, $amount)
    {
        return $this->getTable()->where(['code' => $code, 'amount' => $amount])->count('*') > 0;
    }
}
