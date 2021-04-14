<?php

namespace Crm\ProductsModule\Commands;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\PaymentItem\PostalFeePaymentItem;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Nette\Database\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Calculates average and total amounts of money spent in products module and stores it in user's meta data.
 *
 * These meta data are mainly used by admin widget TotalUserPayments.
 */
class CalculateAveragesCommand extends Command
{
    private $database;

    public function __construct(Context $database)
    {
        parent::__construct();
        $this->database = $database;
    }

    protected function configure()
    {
        $this->setName('products:calculate_averages')
            ->setDescription('Calculate product-related averages');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $paidStatus = PaymentsRepository::STATUS_PAID;
        $productType = ProductPaymentItem::TYPE;
        $postalFeeType = PostalFeePaymentItem::TYPE;

        $this->database->query(<<<SQL
            -- fill empty values for new users
            INSERT IGNORE INTO `user_meta` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
            SELECT `id`, 'product_payments', 0, NOW(), NOW()
            FROM `users`;

            -- calculate & update values
            UPDATE `user_meta`
            INNER JOIN (
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COUNT(DISTINCT(`payments`.`id`)) AS `product_payments_count`
                FROM `payment_items`
                INNER JOIN `payments`
                    ON `payments`.`id` = `payment_items`.`payment_id`
                    AND `payments`.`status` = '$paidStatus'
                WHERE `payment_items`.`type` IN ('$productType', '$postalFeeType')
                GROUP BY `payments`.`user_id`
            ) AS `product_payments`
                ON `user_meta`.`user_id` = `product_payments`.`user_id`
            SET
               `value` = `product_payments`.`product_payments_count`,
               `updated_at` = NOW()
            WHERE
                `key` = 'product_payments'
            ;
SQL
        );

        $this->database->query(<<<SQL
            -- fill empty values for new users
            INSERT IGNORE INTO `user_meta` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
            SELECT `id`, 'product_payments_amount', 0, NOW(), NOW()
            FROM `users`;

            -- calculate & update values
            UPDATE `user_meta`
            INNER JOIN (
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COALESCE(SUM(`payment_items`.`amount` * `payment_items`.`count`), 0) AS `product_payments_amount`
                FROM `payment_items`
                INNER JOIN `payments`
                    ON `payments`.`id` = `payment_items`.`payment_id`
                    AND `payments`.`status` = '$paidStatus'
                WHERE `payment_items`.`type` IN ('$productType', '$postalFeeType')
                GROUP BY `payments`.`user_id`
            ) AS `product_payments`
                ON `user_meta`.`user_id` = `product_payments`.`user_id`
            SET
               `value` = `product_payments`.`product_payments_amount`,
               `updated_at` = NOW()
            WHERE
                `key` = 'product_payments_amount'
            ;
SQL
        );

        return 0;
    }
}
