<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ProductsModule\Repository\OrdersRepository;
use Crm\ProductsModule\Repository\ProductsRepository;
use Nette\Application\UI;
use Nette\Utils\DateTime;

/**
 * Nette listing component using bootstrap table.
 * This component fetches product sales stats
 * and shows them in table for different time intervals.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class ProductStats extends UI\Control
{
    private $templateName = 'products_stats.latte';

    private $productsRepository;

    private $ordersRepository;

    public function __construct(
        ProductsRepository $productsRepository,
        OrdersRepository $ordersRepository
    ) {
        parent::__construct();

        $this->ordersRepository = $ordersRepository;
        $this->productsRepository = $productsRepository;
    }

    public function render()
    {
        $now =  new DateTime();
        $periods = [
            'today' => [(new DateTime())->setTime(0, 0), $now],
            'yesterday' => [(new DateTime('yesterday'))->setTime(0, 0), (new DateTime('yesterday'))->setTime(23, 59, 59)],
            'last_7days' => [(new DateTime('-7 days'))->setTime(0, 0), $now],
            'current_month' => [(new DateTime('first day of this month'))->setTime(0, 0), $now],
            'last_month' => [(new DateTime('first day of previous month'))->setTime(0, 0), (new DateTime('last day of previous month'))->setTime(23, 59, 59)],
            'all' => [null, null]
        ];

        $productStats = $this->productsRepository->getShopProducts(false, false)->fetchAssoc('id');
        foreach ($periods as $periodName => $period) {
            $periodStats = $this->ordersRepository->stats($period[0], $period[1]);
            foreach ($periodStats as $productId => $count) {
                $productStats[$productId]['stats'][$periodName] = $count;
            }
        }

        $productStats = array_filter($productStats, function ($item) {
            return array_key_exists('stats', $item);
        });

        $this->template->productStats = $productStats;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
