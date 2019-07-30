<?php

namespace Crm\ProductsModule\Model;

use Crm\PaymentsModule\Model\PaymentCompleteRedirectResolver;
use Nette\Database\Table\ActiveRow;

class ShopPaymentCompleteRedirectResolver implements PaymentCompleteRedirectResolver
{
    public function wantsToRedirect(ActiveRow $payment, string $status): bool
    {
        if (in_array($status, [self::PAID, self::ERROR])) {
            return !empty($payment->related('order')->fetch());
        }
        return false;
    }

    public function redirectArgs(ActiveRow $payment, string $status): array
    {
        if ($status === self::PAID) {
            return [
                ':Products:Shop:Success',
                ['id' => $payment->variable_symbol],
            ];
        }
        if ($status === self::ERROR) {
            return [
                ':Products:Shop:Error',
            ];
        }
        throw new \Exception('unhandled status when requesting redirectArgs (did you check wantsToRedirect first?): ' . $status);
    }
}
