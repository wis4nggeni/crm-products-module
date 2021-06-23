<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class PostalFeesRepository extends Repository
{
    protected $tableName = 'postal_fees';

    final public function getByCountry($countryId)
    {
        return $this->getTable()->where([':country_postal_fees.country_id' => $countryId, ':country_postal_fees.active' => true])->order('sorting');
    }

    final public function getDefaultByCountry($countryId)
    {
        $row = $this->getByCountry($countryId)->where(['default' => true])->limit(1)->fetch();
        if (!$row) {
            $row = $this->getByCountry($countryId)->order('sorting')->limit(1)->fetch();
        }
        return $row;
    }

    final public function all()
    {
        return $this->getTable();
    }

    final public function add($code, $title, $amount)
    {
        return $this->insert([
            'code' => $code,
            'title' => $title,
            'amount' => $amount,
        ]);
    }

    final public function exists($code, $amount)
    {
        return $this->getTable()->where(['code' => $code, 'amount' => $amount])->count('*') > 0;
    }

    final public function findByCode($code)
    {
        return $this->getTable()->where('code', $code)->fetch();
    }
}
