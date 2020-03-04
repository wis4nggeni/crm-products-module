<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Nette\Database\Context;
use Nette\Utils\DateTime;

class OrdersRepository extends Repository
{
    const STATUS_NEW = 'new'; // order was creates
    const STATUS_PAID = 'paid'; // order was paid (finished payment)
    const STATUS_PENDING = 'pending'; // order was sent to distribution center
    const STATUS_CONFIRMED = 'confirmed'; // order was registered to distribution center
    const STATUS_SENT = 'sent'; // order was sent to user from distribution center
    const STATUS_DELIVERED = 'delivered'; // order was delivered to user
    const STATUS_RETURNED = 'returned'; // order was returned to distribution center
    const STATUS_NOT_SENT = 'not-sent'; // communication with distribution center failed
    const STATUS_PAYMENT_FAILED = 'payment-failed'; // payment failed
    const STATUS_PAYMENT_REFUNDED = 'payment-refunded'; // payment was refunded
    const STATUS_IMPORTED = 'imported'; // imported payment paid in 3rd party system

    protected $tableName = 'orders';

    public function __construct(
        Context $database,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function all()
    {
        return $this->getTable()->order('created_at DESC');
    }

    final public function add($paymentId, $shippingAddressId, $licenceAddressId, $billingAddressId, $postalFee, $note = null)
    {
        return $this->insert([
            'payment_id' => $paymentId,
            'shipping_address_id' => $shippingAddressId,
            'licence_address_id' => $licenceAddressId,
            'billing_address_id' => $billingAddressId,
            'postal_fee_id' => $postalFee ? $postalFee->id : null,
            'note' => $note,
            'status' => static::STATUS_NEW,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }

    final public function getStatusPairs()
    {
        return [
            self::STATUS_NEW => self::STATUS_NEW,
            self::STATUS_PAID => self::STATUS_PAID,
            self::STATUS_PENDING => self::STATUS_PENDING,
            self::STATUS_CONFIRMED => self::STATUS_CONFIRMED,
            self::STATUS_SENT => self::STATUS_SENT,
            self::STATUS_DELIVERED => self::STATUS_DELIVERED,
            self::STATUS_RETURNED => self::STATUS_RETURNED,
            self::STATUS_NOT_SENT => self::STATUS_NOT_SENT,
        ];
    }

    final public function findByPayment($payment)
    {
        return $this->getTable()->where(['payment_id' => $payment->id])->fetch();
    }

    final public function getByUser($userId, $status = [])
    {
        $orders = $this->getTable()->where(['payment.user_id' => $userId])->order('created_at DESC');
        if (!empty($status)) {
            $orders->where(['orders.status' => (array)$status]);
        }

        return $orders;
    }

    final public function stats(DateTime $from = null, DateTime $to = null)
    {
        $selection = $this->getTable()
            ->select('SUM(payment:payment_items.count) AS product_counts, payment:payment_items.product.id AS product_id')
            ->where('payment:payment_items.type = ?', ProductPaymentItem::TYPE)
            ->where('payment:payment_items.product.shop = ?', true)
            ->where('payment.status = ?', PaymentsRepository::STATUS_PAID)
            ->order('payment:payment_items.product.sorting')
            ->group('payment:payment_items.product.id');

        if ($to === null) {
            $to = new DateTime();
        }

        if ($from !== null) {
            $selection->where('payment.paid_at BETWEEN ? AND ?', $from, $to);
        }

        return $selection->fetchPairs('product_id', 'product_counts');
    }
}
