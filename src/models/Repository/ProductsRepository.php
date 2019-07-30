<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\ProductsModule\Distribution\AmountSpentDistribution;
use Crm\ProductsModule\Distribution\PaymentCountsDistribution;
use Crm\ProductsModule\Distribution\ProductDaysFromLastOrderDistribution;
use Crm\ProductsModule\Distribution\ProductShopCountsDistribution;
use Nette\Database\Context;
use Nette\Database\Table\IRow;

class ProductsRepository extends Repository
{
    protected $tableName = 'products';

    private $amountSpentDistribution;

    private $paymentCountDistribution;

    private $productDaysFromLastOrderDistribution;

    private $productShopCountsDistribution;

    public function __construct(
        Context $database,
        AuditLogRepository $auditLogRepository,
        AmountSpentDistribution $amountSpentDistribution,
        PaymentCountsDistribution $paymentCountDistribution,
        ProductDaysFromLastOrderDistribution $productDaysFromLastOrderDistribution,
        ProductShopCountsDistribution $productShopCountsDistribution
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
        $this->amountSpentDistribution = $amountSpentDistribution;
        $this->paymentCountDistribution = $paymentCountDistribution;
        $this->productDaysFromLastOrderDistribution = $productDaysFromLastOrderDistribution;
        $this->productShopCountsDistribution = $productShopCountsDistribution;
    }

    public function all()
    {
        return $this->getTable()->order('-sorting DESC, name ASC');
    }

    public function getByCode($code)
    {
        return $this->getTable()->where(['code' => $code])->fetch();
    }

    public function getShopProducts($visibleOnly = true, $availableOnly = true, $tags = [])
    {
        $where = ['shop' => true];
        if ($visibleOnly === true) {
            $where['visible'] = true;
        }
        if ($availableOnly === true) {
            $where['stock > ?'] = 0;
        }
        if (!empty($tags)) {
            $where[':product_tags.tag_id'] = $tags;
        }

        return $this->getTable()->where($where)->order('sorting');
    }

    public function relatedProducts(IRow $product, $limit = 4)
    {
        $where = [
            'shop' => true,
            'visible' => true,
            'stock > ?' => 0,
            'id != ?' => $product->id
        ];
        return $this->getTable()
            ->where($where)
            ->order('RAND()')
            ->limit($limit);
    }

    public function findByIds($ids)
    {
        return $this->getTable()->where('id', (array)$ids)->fetchAll();
    }

    public function updateSorting($newSorting, $oldSorting = null)
    {
        if ($newSorting == $oldSorting) {
            return;
        }

        if ($oldSorting !== null) {
            $this->getTable()->where('sorting > ?', $oldSorting)->update(['sorting-=' => 1]);
        }

        $this->getTable()->where('sorting >= ?', $newSorting)->update(['sorting+=' => 1]);
    }

    public function userAmountSpentDistribution($levels, $productId)
    {
        return $this->amountSpentDistribution->distribution($this->getDatabase(), $productId, $levels);
    }

    public function userAmountSpentDistributionList($fromLevel, $toLevel, $productId)
    {
        return $this->amountSpentDistribution->distributionList($this->getDatabase(), $productId, $fromLevel, $toLevel);
    }

    public function userPaymentCountsDistribution($levels, $productId)
    {
        return $this->paymentCountDistribution->distribution($this->getDatabase(), $productId, $levels);
    }

    public function userPaymentCountsDistributionList($fromLevel, $toLevel, $productId)
    {
        return $this->paymentCountDistribution->distributionList($this->getDatabase(), $productId, $fromLevel, $toLevel);
    }

    public function productDaysFromLastOrderDistribution($levels, $productId)
    {
        return $this->productDaysFromLastOrderDistribution->distribution($this->getDatabase(), $productId, $levels);
    }

    public function productDaysFromLastOrderDistributionList($fromlevel, $toLevel, $productId)
    {
        return $this->productDaysFromLastOrderDistribution->distributionList($this->getDatabase(), $productId, $fromlevel, $toLevel);
    }

    public function productShopCountsDistribution($levels, $productId)
    {
        return $this->productShopCountsDistribution->distribution($this->getDatabase(), $productId, $levels);
    }

    public function productShopCountsDistributionList($fromlevel, $toLevel, $productId)
    {
        return $this->productShopCountsDistribution->distributionList($this->getDatabase(), $productId, $fromlevel, $toLevel);
    }

    public function decreaseStock(IRow $product, $count = 1)
    {
        $this->update($product, ['stock-=' => $count]);
    }
}
