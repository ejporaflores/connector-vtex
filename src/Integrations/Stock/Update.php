<?php

namespace Vega\Connector\Vtex\Integrations\Stock;

use Vega\Connector\Vtex\Connector;
use Vega\Connector\Vtex\Traits\Clamp;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Class Get
 * @package Vega\Connector\Connectors\Vtex\Orders
 */
class Update extends Connector
{
    use Clamp;

    public const CLAMP_US_UNIT = 25000;

    /**
     * Execute integration
     */
    public function execute()
    {
        $generator = $this->dataLayer->get();
        foreach ($generator as $collection) {
            foreach ($collection as $item) {
                $this->clamp([$this, 'processUpdate'], [$item], self::CLAMP_US_UNIT);
            }
        }
    }

    /**
     * @param $item
     * @throws BindingResolutionException
     */
    protected function processUpdate($item): void
    {
        $data = $item->data;
        if (!$this->isRecordValid($data)) {
            return;
        }
        $key = data_get($data, $this->getCurrentEntityConfig('key'));
        $this->setCurrentSeller($data);
        $uniqueKey = $this->getUniqueKey($key, $data);
        try {
            $data[$this->getCurrentEntityConfig('key')] = $this->callEntity('sku.get_by_ref', ['ref_id' => $key]);
            $this->callEntity('stock.update', $data);
            $this->executionService->success($uniqueKey, $this->getConnectorEntity(), $this->getConnectorAction());
        } catch (\Exception $e) {
            $this->handleError($e, $uniqueKey);
        }
    }
}
