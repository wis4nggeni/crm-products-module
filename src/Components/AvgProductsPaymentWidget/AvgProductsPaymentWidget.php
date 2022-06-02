<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SegmentModule\SegmentWidgetInterface;
use Crm\UsersModule\Repository\UserMetaRepository;
use Nette\Database\Table\ActiveRow;

class AvgProductsPaymentWidget extends BaseWidget implements SegmentWidgetInterface
{
    private string $templateName = 'avg_products_payment_widget.latte';

    private UserMetaRepository $userMetaRepository;
    private CacheRepository $cacheRepository;

    public function __construct(
        WidgetManager $widgetManager,
        UserMetaRepository $userMetaRepository,
        CacheRepository $cacheRepository
    ) {
        parent::__construct($widgetManager);
        $this->userMetaRepository = $userMetaRepository;
        $this->cacheRepository = $cacheRepository;
    }

    public function identifier()
    {
        return 'avgproductspaymentwidget';
    }

    public function render(ActiveRow $segment)
    {
        if (!$this->isWidgetUsable($segment)) {
            return;
        }

        $avgProductPayments = $this->cacheRepository->load($this->getCacheKey($segment));

        $this->template->avgProductPayments = $avgProductPayments->value ?? 0;
        $this->template->updatedAt = $avgProductPayments->updated_at ?? null;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    public function recalculate(ActiveRow $segment, array $userIds): void
    {
        if (!$this->isWidgetUsable($segment)) {
            return;
        }

        $result = $this->userMetaRepository
            ->getTable()
            ->select('COALESCE(SUM(value), 0) AS sum')
            ->where(['key' => 'product_payments', 'user_id' => $userIds])
            ->fetch();

        $value = 0;
        if ($result !== null && count($userIds) !== 0) {
            $value = $result->sum / count($userIds);
        }

        $this->cacheRepository->updateKey($this->getCacheKey($segment), $value);
    }

    private function isWidgetUsable($segment): bool
    {
        return $segment->table_name === 'users';
    }

    private function getCacheKey($segment): string
    {
        return sprintf('segment_%s_avg_products_payment', $segment->id);
    }
}
