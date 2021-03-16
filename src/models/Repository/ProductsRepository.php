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
use Nette\Database\Table\Selection;

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

    final public function all(string $search = null, array $tags = []): Selection
    {
        $all = $this->getTable()->order('-sorting DESC, name ASC');

        if (empty($tags) && ($search === null || empty(trim($search)))) {
            return $all;
        }

        $searchText = "%{$search}%";
        $conditions = [
            'name LIKE ?' => $searchText,
            'code LIKE ?' => $searchText,
            'user_label LIKE ?' => $searchText,
        ];

        // check if searched text is number (replace comma with period; otherwise is_numeric won't work)
        $searchNum = str_replace(',', '.', $search);
        if (is_numeric($searchNum)) {
            $searchFloat = (float) $searchNum;
            $conditions = array_merge($conditions, [
                'price = ?' => $searchFloat,
                'catalog_price = ?' => $searchFloat,
            ]);
        }

        if (!empty($tags)) {
            $all->where(':product_tags.tag_id IN (?)', $tags);
        }

        return $all->whereOr($conditions);
    }

    final public function getByCode($code)
    {
        return $this->getTable()->where(['code' => $code])->fetch();
    }

    final public function getShopProducts($visibleOnly = true, $availableOnly = true, $tag = null)
    {
        $where = ['shop' => true];
        if ($visibleOnly === true) {
            $where['visible'] = true;
        }
        if ($availableOnly === true) {
            $where['stock > ?'] = 0;
        }
        if (isset($tag)) {
            $where[':product_tags.tag_id'] = $tag->id;
        }

        return $this->getTable()->where($where)->order('sorting');
    }

    final public function relatedProducts(IRow $product, $limit = 4)
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

    final public function findByIds($ids)
    {
        return $this->getTable()->where('id', (array)$ids)->fetchAll();
    }

    final public function updateSorting($newSorting, $oldSorting = null)
    {
        if ($newSorting == $oldSorting) {
            return;
        }

        if ($oldSorting !== null) {
            $this->getTable()->where('sorting > ?', $oldSorting)->update(['sorting-=' => 1]);
        }

        $this->getTable()->where('sorting >= ?', $newSorting)->update(['sorting+=' => 1]);
    }

    final public function userAmountSpentDistribution($levels, $productId)
    {
        return $this->amountSpentDistribution->distribution($productId, $levels);
    }

    final public function userAmountSpentDistributionList($fromLevel, $toLevel, $productId)
    {
        return $this->amountSpentDistribution->distributionList($productId, $fromLevel, $toLevel);
    }

    final public function userPaymentCountsDistribution($levels, $productId)
    {
        return $this->paymentCountDistribution->distribution($productId, $levels);
    }

    final public function userPaymentCountsDistributionList($fromLevel, $toLevel, $productId)
    {
        return $this->paymentCountDistribution->distributionList($productId, $fromLevel, $toLevel);
    }

    final public function productDaysFromLastOrderDistribution($levels, $productId)
    {
        return $this->productDaysFromLastOrderDistribution->distribution($productId, $levels);
    }

    final public function productDaysFromLastOrderDistributionList($fromlevel, $toLevel, $productId)
    {
        return $this->productDaysFromLastOrderDistribution->distributionList($productId, $fromlevel, $toLevel);
    }

    final public function productShopCountsDistribution($levels, $productId)
    {
        return $this->productShopCountsDistribution->distribution($productId, $levels);
    }

    final public function productShopCountsDistributionList($fromlevel, $toLevel, $productId)
    {
        return $this->productShopCountsDistribution->distributionList($productId, $fromlevel, $toLevel);
    }

    final public function decreaseStock(IRow &$product, $count = 1)
    {
        $this->update($product, ['stock-=' => $count]);
    }
}
