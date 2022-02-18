<?php

namespace Vega\Connector\Vtex\Integrations\Price;

use Illuminate\Contracts\Container\BindingResolutionException;
use Vega\Connector\Vtex\Connector;
use Vega\Connector\Vtex\Traits\Clamp;

/**
 * Class Get
 * @package Vega\Connector\Connectors\Vtex\Orders
 */
class Update extends Connector
{
    use Clamp;

    public const CLAMP_US_UNIT = 25000;

    /**
     * @return $this
     */
    public function execute()
    {
        $generator = $this->dataLayer->get();
        foreach ($generator as $collection) {
            foreach ($collection as $item) {
                $this->clamp([$this, 'processUpdate'], [$item], self::CLAMP_US_UNIT);
            }
        }
        return $this;
    }

    /**
     * @param $sku
     * @return bool|mixed
     */
    protected function getPriceInfo($sku)
    {
        try {
            $item = $this->callEntity('price.get', ['sku' => $sku]);
            if (is_array($item)) {
                return reset($item);
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
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
            $skuId = $this->callEntity('sku.get_by_ref', ['ref_id' => $key]);
            $data[$this->getCurrentEntityConfig('key')] = $skuId;
            $info = $this->getPriceInfo($skuId);
            if ($info) {
                $data['id'] = $info['id'];
            }
            $this->callEntity('price.update', $data);
            $this->executionService->success(
                $uniqueKey,
                $this->getConnectorEntity(),
                $this->getConnectorAction()
            );
        } catch (\Exception $e) {
            $this->handleError($e, $uniqueKey);
        }
    }
}
