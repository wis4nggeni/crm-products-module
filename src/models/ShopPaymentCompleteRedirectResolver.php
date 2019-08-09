<?php

namespace Crm\ProductsModule\Model;

use Crm\PaymentsModule\Model\PaymentCompleteRedirectResolver;
use Nette\Database\Table\ActiveRow;

class ShopPaymentCompleteRedirectResolver implements PaymentCompleteRedirectResolver
{
    public function wantsToRedirect(?ActiveRow $payment, string $status): bool
    {
        if ($payment && $status === self::PAID) {
            return !empty($payment->related('order')->fetch());
        }
        if ($status === self::ERROR) {
            return true;
        }
        return false;
    }

    public function redirectArgs(?ActiveRow $payment, string $status): array
    {
        if ($payment && $status === self::PAID) {
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
