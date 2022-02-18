<?php

namespace Vega\Connector\Vtex\Integrations\Feed;

use Vega\Connector\Vtex\Connector;
use Vega\Connector\Exceptions\ConnectorException;

class Config extends Connector
{
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
     * @return bool
     */
    protected function executeProcess(): bool
    {
        try {
            $data = (array)$this->getCurrentEntityConfig('data');
            $this->callEntity('feed.config', $data);

            //log execution
            $this->executionService->success(
                'Feed config',
                $this->getConnectorEntity(),
                $this->getConnectorAction()
            );
            return true;
        } catch (\Exception $e) {
            $this->executionService->error(
                'Feed config',
                $this->getConnectorEntity(),
                $this->getConnectorAction(),
                $e->getMessage()
            );
            return false;
        }
    }
}
