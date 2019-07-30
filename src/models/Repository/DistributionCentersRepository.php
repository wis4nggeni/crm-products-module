<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class DistributionCentersRepository extends Repository
{
    const DISTRIBUTION_CENTER_FHB_GROUP = 'kika';
    const DISTRIBUTION_CENTER_DIBUK = 'dibuk';
    const DISTRIBUTION_CENTER_DENNIKN = 'dennikn';

    protected $tableName = 'distribution_centers';

    public function all()
    {
        return $this->getTable();
    }

    public function add($code, $name)
    {
        return $this->insert([
            'code' => $code,
            'name' => $name
        ]);
    }
}
