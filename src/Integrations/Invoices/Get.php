<?php

namespace Vega\Connector\Vtex\Integrations\Invoices;

use Carbon\CarbonInterface;
use Exception;
use Vega\Connector\Vtex\Connector;
use Vega\Connector\Exceptions\ConnectorException;
use Vega\Connector\Vtex\Traits\Clamp;
use Illuminate\Container\Container as LaravelContainer;

/**
 * Class Get
 * Obtener órdenes para facturar desde VTEX, para buscar invoices en apis que no exponen endpoint de listado
 * @package Vega\Connector\Connectors\Vtex\Invoices
 */
class Get extends Connector
{
    use Clamp;

    public const CLAMP_US_UNIT = 13000;
    public const ORDER_STATUS = 'handling';
    public const ORDER_KEY = 'order_id';
    public const START_DATE_DAYS_BACK = 7;
    // ?f_creationDate=creationDate:[2017-01-01T02:00:00.000Z TO 2017-01-02T01:59:59.999Z]
    public const DATE_FORMAT_FROM = 'Y-m-d\T00:00:00.000\Z';
    public const DATE_FORMAT_TO = 'Y-m-d\TH:i:00.000\Z';

    protected $repository;

    /**
     * @var array $orders
     */
    protected $orders;
    /**
     * @var CarbonInterface $dateTo
     */
    protected $dateTo;
    /**
     * @var CarbonInterface $dateFrom
     */
    protected $dateFrom;

    /**
     * @throws ConnectorException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function setup()
    {
        parent::setup();

        $this->setDatesFromAndTo();
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
        return $this->clamp(
            [$this, 'callEntity'],
            [
                "invoices.get",
                [
                    'page' => $page,
                    'status' => $status,
                    'dateFrom' => $this->dateFrom->format(self::DATE_FORMAT_FROM),
                    'dateTo' => $this->dateTo->format(self::DATE_FORMAT_TO)
                ]
            ],
            self::CLAMP_US_UNIT
        );
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

            //push order to data layer
            $this->dataLayer->push($item);

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
     * Paged orders
     * @throws ConnectorException
     */
    protected function getOrders()
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
     * inicializar arrays
     */
    protected function beforeProcess()
    {
        $this->orders = [];
    }

    /**
     * wrapper after process
     */
    protected function afterProcess()
    {
        // no hay acciones after process
    }

    /**
     * after pull paged
     * @param $item
     * @param $result
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function afterPull($item, $result)
    {
        // no hay acciones afterPull
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function setDatesFromAndTo()
    {
        $carbon = LaravelContainer::getInstance()->make(CarbonInterface::class);

        $daysBack = $this->getCurrentEntityConfig('days_back') ?? self::START_DATE_DAYS_BACK;

        $this->dateTo = $carbon->now();
        $this->dateFrom = (clone $this->dateTo)->subDays($daysBack);
    }
}
