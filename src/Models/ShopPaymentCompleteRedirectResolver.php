<?php

namespace Crm\ProductsModule\Models;

use Crm\PaymentsModule\Models\SuccessPageResolver\PaymentCompleteRedirectResolver;
use Nette\Database\Table\ActiveRow;

class ShopPaymentCompleteRedirectResolver implements PaymentCompleteRedirectResolver
{
    public function wantsToRedirect(?ActiveRow $payment, string $status): bool
    {
        if ($payment) {
            if (in_array($status, [self::PAID, self::ERROR, self::FORM], true)) {
                return $payment->related('order')->fetch() !== null;
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
            if ($status === self::FORM) {
                return [
                    ':Products:Shop:NotSettled',
                ];
            }
        }

        throw new \Exception('unhandled status when requesting redirectArgs (did you check wantsToRedirect first?): ' . $status);
    }
}
