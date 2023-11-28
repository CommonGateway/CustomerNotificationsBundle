<?php

namespace CommonGateway\CustomerNotificationsBundle\Service;

use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * This service handles the incoming notifications. Creating email and/or sms messages if configured to do so.
 *
 * @author Conduction.nl <info@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Service
 */
class NotificationsService
{

    /**
     * Configuration array.
     *
     * @var array
     */
    private array $configuration;

    /**
     * Data array.
     *
     * @var array
     */
    private array $data;

    /**
     * The entity manager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * The plugin logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * The Call Service.
     *
     * @var CallService
     */
    private CallService $callService;

    /**
     * The Gateway Resource Service.
     *
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * The Cache Service.
     *
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * The event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;


    /**
     * @param EntityManagerInterface   $entityManager   The Entity Manager.
     * @param LoggerInterface          $pluginLogger    The plugin version of the logger interface.
     * @param CallService              $callService     The Call Service
     * @param GatewayResourceService   $resourceService The Gateway Resource Service.
     * @param CacheService             $cacheService    The Cache Service.
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $pluginLogger,
        CallService $callService,
        GatewayResourceService $resourceService,
        CacheService $cacheService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->entityManager   = $entityManager;
        $this->logger          = $pluginLogger;
        $this->callService     = $callService;
        $this->resourceService = $resourceService;
        $this->cacheService    = $cacheService;
        $this->eventDispatcher = $eventDispatcher;
        $this->configuration   = [];
        $this->data            = [];

    }//end __construct()


    /**
     * Handles incoming notification api-call and is responsible for generating a response.
     * Might also send an email and/or sms after, etc.
     *
     * @param array $data          The data array
     * @param array $configuration The configuration array
     *
     * @return array A handler must ALWAYS return an array
     */
    public function notificationsHandler(array $data, array $configuration): array
    {
        if ($data['method'] !== 'POST') {
            return $data;
        }

        $this->data          = $data;
        $this->configuration = $configuration;

        if ($this->handleExtraConditions() === false) {
            return $data;
        }

        $this->logger->debug("NotificationsBundler -> NotificationsService -> notificationsHandler()", ['plugin' => 'common-gateway/customer-notifications-bundle']);

        $this->handleEmail();
        $this->handleSMS();
        $this->createObject();

        // todo: improve response, depending on what this notification triggered? ',email send, ContactMoment Object created', etc.
        $response         = ['Message' => 'Notification received.'];
        $data['response'] = new Response(json_encode($response), 200, ['Content-type' => 'application/json']);

        return $data;

    }//end notificationsHandler()


    /**
     * If the action configuration contains extraConditions, check if these conditions are met.
     *
     * @return bool True if conditions are met, else false.
     */
    private function handleExtraConditions(): bool
    {
        // If there are no extra conditions return true.
        if (empty($this->configuration['extraConditions']) === true) {
            return true;
        }

        foreach ($this->configuration['extraConditions'] as $dataKey => $condition) {
            if (isset($this->data[$dataKey]) === false) {
                $this->logger->error("ExtraCondition $dataKey does not exist in the action data array.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
                return false;
            }

            switch ($dataKey) {
            case 'body.hoofdObject':
            case 'body.resourceUrl':
                return $this->handleUrlCondition($dataKey, $condition);
            default:
                $this->logger->error("Couldn't find any logic for extraCondition $dataKey.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
                break;
            }
        }

        return true;

    }//end handleExtraConditions()


    /**
     * Check if expected conditions are met for an $this->data field that contains an url.
     * First does a get call on this url, then checks if response matches the given $condition array.
     *
     * @param string $dataKey   The key used for checking a specific field in the $this->data array.
     * @param array  $condition The condition (array) to be met.
     *
     * @return bool True if condition is met, else false.
     */
    private function handleUrlCondition(string $dataKey, array $condition): bool
    {
        // Todo: Maybe we could/should support multiple Sources instead of one?
        if (empty($condition['commongatewaySourceRef']) === true) {
            $this->logger->error("ExtraCondition $dataKey is missing the key = 'commongatewaySourceRef' (with = a Source reference)", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return false;
        }

        $source = $this->resourceService->getSource($condition['commongatewaySource'], 'open-catalogi/open-catalogi-bundle');
        if ($source === null) {
            return false;
        }

        unset($condition['commongatewaySource']);

        $url      = $this->data[$dataKey];
        $endpoint = str_replace($source->getLocation(), '', $url);

        try {
            $response = $this->callService->call($source, $endpoint);
        } catch (Exception $e) {
            $this->logger->error("Error when trying to fetch $url while checking extraConditions: {$e->getMessage()}", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return false;
        }

        foreach ($condition as $key => $value) {
            if (isset($response[$key]) === false || $response[$key] != $value) {
                $this->logger->debug("ExtraCondition $dataKey"."[$key] != $value.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
                return false;
            }
        }

        return true;

    }//end handleUrlCondition()


    /**
     * If emailConfig has been configured in the Action->configuration.
     * This function handles getting info for an email and throwing the email event that will trigger the actual email sending.
     *
     * @return void
     */
    private function handleEmail(): ?array
    {
        // If there are is no emailConfig, return.
        if (empty($this->configuration['emailConfig']) === true) {
            return null;
        }

        $emailConfig = $this->configuration['emailConfig'];

        if (empty($emailConfig['throw']) === true) {
            $this->logger->error("Action configuration emailConfig is missing the key = 'throw'.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return null;
        }

        // Todo: duplicate code with sms?
        if (empty($emailConfig['useObjectEntityData']) === false) {
            // todo...
            // Object id?
            $id     = '';
            $object = $this->cacheService->getObject($id, $emailConfig['useObjectEntityData']);
        }

        // Throw email event
        $event = new ActionEvent('commongateway.action.event', ($object ?? []), $emailConfig['throw']);

        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

        return $event->getData();

    }//end handleEmail()


    /**
     * If smsConfig has been configured in the Action->configuration.
     * This function handles getting info for a sms and throwing the sms event that will trigger the actual sms sending.
     *
     * @return void
     */
    private function handleSMS()
    {
        // If there are is no smsConfig, return.
        if (empty($this->configuration['smsConfig']) === true) {
            return;
        }

        $smsConfig = $this->configuration['smsConfig'];

        if (empty($smsConfig['throw']) === true) {
            $this->logger->error("Action configuration smsConfig is missing the key = 'throw'.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return;
        }

        // Todo: duplicate code with email?
        if (empty($smsConfig['useObjectEntityData']) === false) {
            // todo...
            // Object id?
            $id     = '';
            $object = $this->cacheService->getObject($id, $smsConfig['useObjectEntityData']);
        }

        // Throw sms event
        $event = new ActionEvent('commongateway.action.event', ($object ?? []), $smsConfig['throw']);

        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

        return $event->getData();

    }//end handleSMS()


    /**
     * If createObjectConfig has been configured in the Action->configuration.
     * This function will create an ObjectEntity using data from notification or other configured objects.
     *
     * @return void
     */
    private function createObject()
    {
        // If there are is no createObjectConfig, return.
        if (empty($this->configuration['createObjectConfig']) === true) {
            return;
        }

        $createObjectConfig = $this->configuration['createObjectConfig'];

        $schema = $this->resourceService->getSchema($createObjectConfig['schema'], 'open-catalogi/open-catalogi-bundle');
        if ($schema === null) {
            return;
        }

        // todo...
        // Input for object creation?
        // Find (& do) Mapping
        // Create ObjectEntity
        return;

    }//end createObject()


}//end class
