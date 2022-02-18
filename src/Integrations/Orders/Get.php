<?php

namespace Vega\Connector\Vtex\Integrations\Orders;

use Exception;
use Illuminate\Http\Response;
use Vega\Connector\Vtex\Connector;
use Vega\Connector\Exceptions\ConnectorException;
use Vega\Connector\Vtex\Traits\Clamp;

/**
 * Class Get
 * @package Vega\Connector\Connectors\Vtex\Orders
 */
class Get extends Connector
{
    use Clamp;

    public const CLAMP_US_UNIT = 13000;
    public const ORDER_STATUS = 'ready-for-handling';
    public const ORDER_KEY = 'order_id';
    public const PAGED_MODE = 'Paged';
    public const FEED_MODE = 'Feed';
    public const FEED_MAX_CALLS = 10;
    public const FEED_MAX_HANDLES = 10;

    protected $repository;

    /**
     * @var array $orders
     */
    protected $orders;

    /**
     * @var array $handles
     */
    protected $handles;

    /**
     * @var string $mode
     */
    protected $mode;

    protected $feedConfig;

    /**
     * @throws ConnectorException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function setup()
    {
        parent::setup();

        $this->mode = self::PAGED_MODE;
        if ($this->getConfig('orders_mode') == 'feed') {
            $this->mode = self::FEED_MODE;
            $this->feedConfig = $this->getEntity('feed.get');
        }
    }

    /**
     * @throws ConnectorException
     */
    public function execute()
    {
        // marketplace process
        $this->executeProcess();
        if ($this->subSellers) {
            foreach ($this->subSellers as $seller) {
                $this->setCurrentSellerFromModel($seller);
                // sellers process
                $this->executeProcess();
            }
        }
    }

    /**
     * @throws ConnectorException
     */
    protected function executeProcess()
    {
        $this->beforeProcess();
        $this->getOrders();

        foreach ($this->orders as $item) {
            $result = $this->clamp([$this, 'processPull'], [$item], self::CLAMP_US_UNIT);
            $this->afterPull($item, $result);
        }

        $this->afterProcess();
    }

    /**
     * @return bool
     */
    private function canConfirm()
    {
        return (bool)$this->getCurrentEntityConfig('confirm');
    }

    /**
     * @param $item
     * @return mixed
     */
    protected function getItemKey($item)
    {
        return (data_get($item, $this->getCurrentEntity('key')) ?? data_get($item, self::ORDER_KEY));
    }

    /**
     * @param $page
     * @return mixed
     * @throws ConnectorException
     */
    protected function getOrderPage($page)
    {
        $status = $this->getCurrentEntity('status') ?? self::ORDER_STATUS;
        //fetch orders collection
        // return $this->callEntity("orders.get", ['page' => $page, 'status' => $status]);
        return $this->clamp(
            [$this, 'callEntity'],
            ["orders.get", ['page' => $page, 'status' => $status]],
            self::CLAMP_US_UNIT
        );
    }

    /**
     * @param $items
     * @return mixed
     * @throws ConnectorException
     */
    protected function getSkuData($items)
    {
        foreach ($items as &$detailItem) {
            $skuData = $this->callEntity('sku.get_by_id', ['sku' => $detailItem['id']]);
            $detailItem = array_merge($detailItem, $skuData);
        }

        return $items;
    }

    /**
     * @param $orderReference
     * @return boolean
     */
    protected function processPull($orderReference): bool
    {
        $key = data_get($orderReference, $this->getCurrentEntityConfig('key'));
        try {
            //fetch order entity
            $item = $this->callEntity("order.get", ['order_id' => $key]);

            if ($this->getCurrentEntityConfig('get_sku_data_by_id')) {
                $item['items'] = $this->getSkuData($item['items']);
            }

            //@todo refactor not take the model
            if (array_key_exists('customer_email', $item)) {
                $email = $this->callEntity(
                    "email.get",
                    ['account' => $this->getCurrentAccountCode(), 'alias' => $item['customer_email']]
                );

                $item = array_merge($item, $email);
            }
            //push order to data layer
            $this->dataLayer->push($item);

            if ($this->canConfirm()) {
                try {
                    $this->callEntity("invoices.confirm", $item, true);
                } catch (ConnectorException $ce) {
                    if ($ce->getCode() != Response::HTTP_CONFLICT) {
                        throw $ce;
                    }
                }
            }
            //log execution
            $this->executionService->success(
                $key,
                $this->getConnectorEntity(),
                $this->getConnectorAction()
            );
            return true;
        } catch (Exception $e) {
            $this->executionService->error(
                $key,
                $this->getConnectorEntity(),
                $this->getConnectorAction(),
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * get orders wrapper
     */
    protected function getOrders()
    {
        $this->orders = [];
        $realMethod = 'getOrders' . $this->mode;
        $this->$realMethod();
    }

    /**
     * Paged orders
     * @throws ConnectorException
     */
    protected function getOrdersPaged()
    {
        $page = 1;
        do {
            $response = $this->getOrderPage($page);
            foreach ($response[self::RESULT_NODE] as $item) {
                $this->orders[] = $item;
            }
            $pages = data_get($response, 'paging.pages');
            $page++;
        } while ($page <= $pages);
    }

    /**
     * Feed orders
     * @throws ConnectorException
     */
    protected function getOrdersFeed()
    {
        $maxCalls = $this->feedConfig->maxcalls ?? self::FEED_MAX_CALLS;
        $orderKey = $this->getCurrentEntityConfig('key');
        $calls = 0;
        while ($calls < $maxCalls && $items = $this->callEntity('feed.get')) {
            $calls++;
            foreach ($items as $item) {
                // matcheamos con la key de orders
                $item[$orderKey] = $item[$this->feedConfig->key];
                // por nro de orden para evitar eventuales repeticiones al pisar
                $this->orders[$item[$this->feedConfig->key]] = $item;
            }
        }
    }

    /**
     * inicializar arrays
     */
    protected function beforeProcess()
    {
        $this->orders = [];
        $this->handles = [];
    }

    /**
     * wrapper after process
     */
    protected function afterProcess()
    {
        $realMethod = 'afterProcess' . $this->mode;
        $this->$realMethod();
    }

    /**
     * after process paged
     */
    protected function afterProcessPaged()
    {
        // paginado no hay acciones after process
    }

    /**
     * @throws ConnectorException
     */
    protected function afterProcessFeed()
    {
        $handles = array_chunk($this->handles, static::FEED_MAX_HANDLES);
        if (count($handles)) {
            foreach ($handles as $handleChunk) {
                $this->callEntity('feed.confirm', ['handles' => $handleChunk]);
            }
        }
    }

    /**
     * wrapper after pull
     * @param $item
     * @param $result
     */
    protected function afterPull($item, $result)
    {
        $realMethod = 'afterPull' . $this->mode;
        $this->$realMethod($item, $result);
    }

    /**
     * after pull paged
     * @param $item
     * @param $result
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function afterPullPaged($item, $result)
    {
        // paginado no hay acciones afterPull
    }

    /**
     * after pull feed
     * @param $item
     * @param $result
     */
    protected function afterPullFeed($item, $result)
    {
        if ($result) {
            $this->handles[] = $item['handle'];
        }
    }
}
