<?php

namespace Vega\Connector\Vtex;

use Vega\Connector\AbstractConnectorProvider;

class VtexServiceProvider extends AbstractConnectorProvider
{
    /**
     * Module name
    @var string
     */
    protected $moduleName = Connector::MODULE_NAME;

    /**
     * Module Path
     * @var string
     */
    protected $modulePath = __DIR__;

    /**
     * Called after all modules are loaded
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        parent::boot();
    }
}
