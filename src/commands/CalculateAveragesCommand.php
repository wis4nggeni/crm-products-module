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
            INSERT INTO user_meta (`user_id`,`key`,`value`,`created_at`,`updated_at`)
            SELECT
                id,
                'product_payments',
                (
                    SELECT COUNT(DISTINCT(payments.id))
                    FROM payments
                    INNER JOIN payment_items ON payment_items.payment_id = payments.id AND payment_items.type IN ('$productType', '$postalFeeType')
                    WHERE
                        payments.status='$paidStatus'
                        AND payments.user_id = users.id
                ),
                NOW(),
                NOW()
            FROM users
            ON DUPLICATE KEY UPDATE `updated_at`=NOW(), `value`=VALUES(value);
SQL
        );

        $this->database->query(<<<SQL
            INSERT INTO user_meta (`user_id`,`key`,`value`,`created_at`,`updated_at`)
            SELECT
                id,
                'product_payments_amount',
                (
                    SELECT SUM(payment_items.amount * payment_items.count)
                    FROM payments
                    INNER JOIN payment_items ON payment_items.payment_id = payments.id AND payment_items.type IN ('$productType', '$postalFeeType')
                    WHERE
                        payments.status='$paidStatus'
                        AND payments.user_id = users.id
                ),
                NOW(),
                NOW()
            FROM users
            ON DUPLICATE KEY UPDATE `updated_at`=NOW(), `value`=VALUES(value);
SQL
        );

        return 0;
    }
}
