<?php

namespace Vega\Connector\Vtex;

use Vega\Connector\Rest\Connector as Rest;
use Vega\Core\Helpers\Data;
use Vega\Connector\Exceptions\ConnectorException;
use Illuminate\Database\Eloquent\Collection;
use Vega\Connector\Vtex\Models\AccountSeller;
use Illuminate\Container\Container as LaravelContainer;
use Vega\Validation\HandlerInterface as ValidationHandler;
use Illuminate\Contracts\Container\BindingResolutionException;
use Vega\Validation\ConfigurationInterface as ValidationConfiguration;
use Vega\Validation\ResultInterface as ValidationResult;
use Vega\Validation\ValidationException;

abstract class Connector extends Rest
{
    public const VERSION = '2.0.0';
    public const MODULE_NAME = 'Vtex';

    public const RESULT_NODE = 'list';

    public const MAX_CONCURRENCE = 2;

    public const CONNECTORS_PATH = 'Connectors';
    public const VALIDATIONS_PATH = 'Validations';

    /**
     * @var string
     */
    protected $requestDataType = 'body';

    /**
     * @var null|array|object
     */
    protected $currentEntity = null;

    /**
     * @var $subSellers Collection
     */
    protected $subSellers = null;

    protected $currentSeller = null;

    /**
     * @var ValidationHandler
     */
    protected $validationHandler;

    /**
     * @throws ConnectorException
     * @throws BindingResolutionException
     */
    protected function setup()
    {
        $this->setBaseUri();
        $this->addHeader('Content-Type', 'application/json');
        $this->auth($this->getConfig('app_key'), $this->getConfig('app_token'));
        parent::setup();
        $this->currentEntity = $this->getEntity($this->getConnectorEntity());

        if ((bool)$this->getCurrentEntityConfig('use_subaccounts')) {
            $this->subSellers = $this->getAccountSellers();
        }

        $this->validationHandler = LaravelContainer::getInstance()->make(ValidationHandler::class);
    }

    protected function auth($appKey, $appToken)
    {
        $this->addHeader('X-VTEX-API-AppKey', $appKey);
        $this->addHeader('X-VTEX-API-AppToken', $appToken);
    }

    /**
     * @return bool
     */
    protected function isValidResponse()
    {
        return in_array($this->lastResponse->getStatusCode(), [200, 201, 204]);
    }

    /**
     * @param \Exception $exception
     * @param $key
     */
    protected function handleError(\Exception $exception, $key)
    {
        $message = $exception->getMessage();
        if ($exception instanceof ConnectorException && $exception->hasResponse()) {
            $response = $exception->getResponse();
            $error = data_get($response, "error");
            $message = data_get($error, "message");
        }
        $this->executionService->error(
            $key ?? '-1',
            $this->getConnectorEntity(),
            $this->getConnectorAction(),
            $message
        );
    }

    /**
     * @param $key
     * @return mixed
     * No confundir currentEntity (ej: orders) con currentEntityConfig (ej: orders.get) ¯\_(ツ)_/¯
     */
    protected function getCurrentEntity($key)
    {
        return data_get($this->currentEntity, $key);
    }

    /**
     * Set BaseUri from Config
     * @param string|null $site
     * @return Connector
     */
    protected function setBaseUri($site = null)
    {
        $baseUri = $this->getConfig('base_uri');
        Data::replace(
            $baseUri,
            [
                'site' => $site ?? $this->getConfig('site')
            ]
        );
        parent::setBaseUri($baseUri);

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getAccountSellers()
    {
        return AccountSeller::where('account_id', $this->account->id)->active()->get();
    }

    /**
     * @param $sellerCode
     * @return AccountSeller
     * @throws ConnectorException
     */
    protected function getSubSeller($sellerCode)
    {
        if (!$this->subSellers || $this->subSellers->isEmpty()) {
            return null;
        }

        $seller = $this->subSellers->where('code', $sellerCode)->first();
        if (!$seller) {
            throw new ConnectorException(get_called_class() . " $sellerCode seller not found on DB");
        }

        return $seller;
    }

    /**
     * @param $data
     */
    protected function setCurrentSeller($data)
    {
        if ((bool)$this->getCurrentEntityConfig('use_subaccounts')) {
            $key = $this->getConfig('subaccounts.subaccount_key') ?? 'seller';
            $this->currentSeller = $data[$key] ?? null;
            if ($this->currentSeller == $this->getConfig('subaccounts.marketplace_value')) {
                $this->currentSeller = null;
            }
        }
    }

    /**
     * @param $seller AccountSeller
     */
    protected function setCurrentSellerFromModel($seller)
    {
        if ((bool)$this->getCurrentEntityConfig('use_subaccounts')) {
            $this->currentSeller = $seller->getAttribute('code');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEntity($entityPath)
    {
        $entity = parent::getEntity($entityPath);
        $this->setBaseUri();
        $this->auth($this->getConfig('app_key'), $this->getConfig('app_token'));

        $this->overrideEntity($entity);

        return $entity;
    }

    /**
     * @param $entity
     * @throws ConnectorException
     */
    protected function overrideEntity(&$entity): void
    {
        $site = $this->getConfig('site') ?? $this->getConfig('account');
        if ($this->currentSeller) {
            /**
             * @var AccountSeller $seller
             */
            $seller = $this->getSubSeller($this->currentSeller);

            $site = $seller->getAttribute('site') ?? $seller->getAttribute('code');
            $this->auth($seller->getAttribute('appkey'), $seller->getAttribute('apptoken'));
        }
        $baseUri = data_get($entity, 'base_uri') ?? $this->getConfig('base_uri');
        Data::replace($baseUri, ['site' => $site]);
        data_set($entity, 'base_uri', $baseUri);
    }

    /**
     * @return string
     */
    protected function getCurrentAccountCode()
    {
        return $this->currentSeller ?? $this->getConfig('account');
    }

    /**
     * @param $result
     * @return ValidationResult
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    protected function validationHandle($result): ValidationResult
    {
        /** @var ValidationConfiguration $config */
        $config = LaravelContainer::getInstance()->make(ValidationConfiguration::class);

        $configFile = $this->getValidationsFullPath() . $this->getCurrentEntityConfig('validation');

        $config->load($configFile);

        // Validate the result
        return $this->validationHandler->handle($config, (object)$result);
    }

    /**
     * @param $data
     * @return bool
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    protected function isRecordValid($data): bool
    {
        if ($this->getCurrentEntityConfig('validation')) {
            $validation = $this->validationHandle($data);
            return $validation->isValid();
        }
        return true;
    }

    /**
     * @return string
     */
    protected function getValidationsFullPath()
    {
        return
            self::CONNECTORS_PATH .
            DIRECTORY_SEPARATOR .
            $this->getConnectorName() .
            DIRECTORY_SEPARATOR .
            self::VALIDATIONS_PATH .
            DIRECTORY_SEPARATOR;
    }
}
