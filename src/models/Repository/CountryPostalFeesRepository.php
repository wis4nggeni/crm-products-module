<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class CountryPostalFeesRepository extends Repository
{
    protected $tableName = 'country_postal_fees';

    final public function add($countryId, $postalFeeId, $sorting = 10, $default = false)
    {
        return $this->insert([
            'country_id' => $countryId,
            'postal_fee_id' => $postalFeeId,
            'sorting' => $sorting,
            'default' => $default,
        ]);
    }

    final public function exists($countryId, $postalFeeId)
    {
        return $this->getTable()->where(['country_id' => $countryId, 'postal_fee_id' => $postalFeeId])->count('*') > 0;
    }

    final public function findAllByPostalFeeId($postalFeeId)
    {
        return $this->getTable()->where('postal_fee_id', $postalFeeId)->fetchAll();
    }
}
