<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;

class PostalFeesRepository extends Repository
{
    protected $tableName = 'postal_fees';

    final public function getByCountry($countryId)
    {
        return $this->getTable()->where([':country_postal_fees.country_id' => $countryId, ':country_postal_fees.active' => true])->order('sorting');
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
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }

    final public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new \DateTime();
        return parent::update($row, $data);
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
