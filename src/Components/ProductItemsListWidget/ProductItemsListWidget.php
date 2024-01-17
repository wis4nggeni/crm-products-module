<?php

namespace Crm\ProductsModule\Components\ProductItemsListWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ProductsModule\PaymentItem\PostalFeePaymentItem;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Nette\Database\Table\ActiveRow;

/**
 * Widget used in payments listing.
 * Renders single payment item list item.
 * Used in foreach.
 *
 * @package Crm\ProductsModule\Components
 */
class ProductItemsListWidget extends BaseLazyWidget
{
    private $templateName = 'product_items_list_widget.latte';

    public function identifier()
    {
        return 'productitemslistwidget';
    }

    public function render(ActiveRow $paymentItem)
    {
        if ($paymentItem->type !== ProductPaymentItem::TYPE && $paymentItem->type !== PostalFeePaymentItem::TYPE) {
            return;
        }

        $this->template->paymentItem = $paymentItem;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
