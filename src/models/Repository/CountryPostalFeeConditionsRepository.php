<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;

class CountryPostalFeeConditionsRepository extends Repository
{
    protected $tableName = 'country_postal_fee_conditions';

    /**
     * @param IRow $countryPostalFeeRow
     * @param string $code - Unique identification of condition
     * @param string $value - Represents configurable value that is checked during the condition evaluation
     *
     * @return bool|int|IRow
     */
    final public function add(IRow $countryPostalFeeRow, string $code, string $value)
    {
        return $this->insert([
            'country_postal_fee_id' => $countryPostalFeeRow->id,
            'code' => $code,
            'value' => $value,
            'created_at' => new \DateTime(),
        ]);
    }

    final public function getByCountryPostalFeeAndCode(IRow $countryPostalFeeRow, string $code)
    {
        return $this->getTable()->where([
            'country_postal_fee_id' => $countryPostalFeeRow->id,
            'code' => $code,
        ])->fetch();
    }
}
