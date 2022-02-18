<?php

namespace Vega\Connector\Vtex\Integrations\Invoices;

use Illuminate\Contracts\Container\BindingResolutionException;
use Vega\Connector\Vtex\Connector;
use Vega\Connector\Vtex\Traits\Clamp;

/**
 * Class Create
 * @package Vega\Connector\Vtex\Integrations\Invoices
 */
class Create extends Connector
{
    use Clamp;

    public const CLAMP_US_UNIT = 25000;

    public function execute()
    {
        $generator = $this->dataLayer->get();
        foreach ($generator as $collection) {
            foreach ($collection as $item) {
                $this->clamp([$this, 'processCreate'], [$item], self::CLAMP_US_UNIT);
            }
        }
    }

    /**
     * @param $entity
     * @return bool
     */
    protected function canChangeToStartHandling($entity): bool
    {
        if (
            (data_get($entity, 'status') == $this->getCurrentEntityConfig('status_start_handling'))
            || (data_get($entity, 'status') == $this->getCurrentEntityConfig('status_handling'))
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param array $entity
     * @return bool
     */
    protected function isInvoiced($entity): bool
    {
        return (data_get($entity, 'status') == $this->getCurrentEntityConfig('status_invoiced'));
    }

    /**
     * @param array $entity
     * @return bool
     */
    protected function isReady($entity): bool
    {
        return (data_get($entity, 'status') == $this->getCurrentEntityConfig('status_ready'));
    }

    /**
     * @param $item
     * @throws BindingResolutionException
     */
    protected function processCreate($item): void
    {
        $data = $item->data;
        if (!$this->isRecordValid($data)) {
            return;
        }
        $key = data_get($data, $this->getCurrentEntityConfig('key'));
        try {
            $this->setCurrentSeller($data);
            $entity = $this->callEntity("order.get", ['order_id' => $key]);
            if ($this->isInvoiced($entity)) {
                $this->executionService->unchanged(
                    $key,
                    $this->getConnectorEntity(),
                    $this->getConnectorAction(),
                    'Order invoiced, no action will be taken.'
                );
                return;
            }

            if ($this->canChangeToStartHandling($entity)) {
                $this->callEntity("invoices.confirm", $data, true);
            }

            $items = data_get($entity, 'items');
            if ($items) {
                $data['items'] = $items;
            }

            $this->callEntity("invoices.create", $data);

            $this->executionService->success(
                $key,
                $this->getConnectorEntity(),
                $this->getConnectorAction()
            );
        } catch (\Exception $e) {
            $this->handleError($e, $key);
        }
    }
}
