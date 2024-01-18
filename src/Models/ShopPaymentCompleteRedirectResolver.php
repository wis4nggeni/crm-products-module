<?php

namespace Crm\ProductsModule\Models;

use Crm\PaymentsModule\Models\SuccessPageResolver\PaymentCompleteRedirectResolver;
use Nette\Database\Table\ActiveRow;

class ShopPaymentCompleteRedirectResolver implements PaymentCompleteRedirectResolver
{
    public function wantsToRedirect(?ActiveRow $payment, string $status): bool
    {
        if ($payment) {
            if ($status === self::PAID) {
                return !empty($payment->related('order')->fetch());
            }
            if ($status === self::ERROR) {
                return !empty($payment->related('order')->fetch());
            }
        }

        return false;
    }

    public function redirectArgs(?ActiveRow $payment, string $status): array
    {
        if ($payment) {
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
        }

        throw new \Exception('unhandled status when requesting redirectArgs (did you check wantsToRedirect first?): ' . $status);
    }
}
