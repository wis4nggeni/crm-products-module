<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class PaymentGiftCouponsRepository extends Repository
{
    const STATUS_NOT_SENT = 'not_sent';
    const STATUS_SENT = 'sent';

    const USER_SOURCE_GIFT_COUPON = 'gift_coupon';

    protected $tableName = 'payment_gift_coupons';

    public function add($paymentId, $productId, $email, DateTime $startsAt)
    {
        return $this->insert([
            'payment_id' => $paymentId,
            'product_id' => $productId,
            'email' => $email,
            'starts_at' => $startsAt,
            'status' => self::STATUS_NOT_SENT
        ]);
    }

    public function getAllNotSentAndActive()
    {
        return $this->getTable()
            ->where(['status' => self::STATUS_NOT_SENT])
            ->where('starts_at <= ?', new DateTime());
    }

    public function findAllBySubscriptions(array $subscriptonsIDs)
    {
        return $this->getTable()->where(['subscription_id' => $subscriptonsIDs])->fetchAll();
    }
}
