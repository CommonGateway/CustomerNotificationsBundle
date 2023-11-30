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

        $message = 'Notification received';

        $email = $this->handleEmail();
        if ($email !== null) {
            $message = $message.", email send";
        }

        $sms = $this->handleSMS();
        if ($sms !== null) {
            $message = $message.", sms send";
        }

        $object = $this->handleObject();
        if ($object !== null) {
            $message = $message.", object created";
        }

        $response         = ['Message' => $message.'.'];
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

        $response = $this->callSource(
            [
                'source'  => $condition['commongatewaySourceRef'],
                'dataKey' => $dataKey,
            ],
            'while checking extraConditions'
        );

        unset($condition['commongatewaySourceRef']);
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
     * @return array|null Return data from the thrown event. Or null.
     */
    private function handleEmail(): ?array
    {
        if (empty($this->configuration['emailConfig']) === true) {
            return null;
        }

        $emailConfig = $this->configuration['emailConfig'];

        if (empty($emailConfig['throw']) === true) {
            $this->logger->error("Action configuration emailConfig is missing the key = 'throw'.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return null;
        }

        // Let's see if we need to get an object to use its data for creating an email message.
        if (empty($emailConfig['getObjectDataConfig']) === false) {
            $object = $this->getObject($emailConfig['getObjectDataConfig']);

            // Set $object in $this->configuration to be re-used in SMSConfig, if configured to do so.
            $this->configuration['emailConfig']['getObjectDataConfig']['SMSSameAsEmail'] = $object;
            
            // Todo: do we want to continue sending an email if we didn't find an object?
        }

        // Throw email event
        return $this->throwEvent($emailConfig, ($object ?? null));

    }//end handleEmail()


    /**
     * If smsConfig has been configured in the Action->configuration.
     * This function handles getting info for a sms and throwing the sms event that will trigger the actual sms sending.
     *
     * @return array|null Return data from the thrown event. Or null.
     */
    private function handleSMS(): ?array
    {
        if (empty($this->configuration['smsConfig']) === true) {
            return null;
        }

        $smsConfig = $this->configuration['smsConfig'];

        if (empty($smsConfig['throw']) === true) {
            $this->logger->error("Action configuration smsConfig is missing the key = 'throw'.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return null;
        }

        // Let's see if we need to get an object to use its data for creating an SMS message.
        if (empty($smsConfig['getObjectDataConfig']) === false) {
            // If getObjectDataConfig for sms is configured to re-use the same object as for email, do so.
            if ($smsConfig['getObjectDataConfig'] === 'sameAsEmail'
                && empty($this->configuration['emailConfig']['getObjectDataConfig']['SMSSameAsEmail']) === false
            ) {
                $object = $this->configuration['emailConfig']['getObjectDataConfig']['SMSSameAsEmail'];
            }

            if (empty($object) === true) {
                $object = $this->getObject($smsConfig['getObjectDataConfig']);
            }
            
            // Todo: do we want to continue sending an sms if we didn't find an object?
        }

        // Throw sms event
        return $this->throwEvent($smsConfig, ($object ?? null));

    }//end handleSMS()


    /**
     * If getObjectDataConfig has been configured in the Action->configuration.
     * This function uses email or SMS configuration to get object data, to use later for email/sms creation (to pass through the event thrown).
     *
     * @param array $config The EmailConfig or SMSConfig.
     *
     * @return array|null The object found or null.
     */
    private function getObject(array $config): ?array
    {
        if ($this->validateConfigArray(['notificationProperty|or|sourceEndpoint', 'searchSchemas', 'searchQuery'], 'getObjectDataConfig', $config,) === false) {
            return null;
        }
        
        $filter = $config['searchQuery'];

        if (empty($config['sourceEndpoint']) === true) {
            $config['dataKey'] = $config['notificationProperty'];
        }
        
        $filter            = $this->addSourceDataToFilter($filter, $config);
        if ($filter === null) {
            return null;
        }

        $objects = $this->cacheService->searchObjects(null, $filter, $config['searchSchemas']);
        if (empty($objects) === true || count($objects['results']) === 0) {
            $this->logger->error("Couldn't find an object to use for email and/or sms data.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return null;
        }

        if (count($objects['results']) > 1) {
            $this->logger->warning("Found more than one object to use for email and/or sms data.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
        }

        return $objects['results'][0];

    }//end getObject()


    /**
     * This function will update the given $filter array so that it contains the correct filter values, using data from sources outside the gateway.
     * If configured correctly to do so. Note that this is a function with recursion.
     *
     * @param array $filter The filter array to update and add data gathered data from one or more Sources to.
     * @param array $config The 'getObjectDataConfig' config (For EmailConfig or SMSConfig, or config used for recursion).
     *
     * @return array|null The updated $filter array.
     */
    private function addSourceDataToFilter(array $filter, array $config): ?array
    {
        if (empty($config['sourceProperties']) === true) {
            $this->logger->error("The key sourceProperties does not exist or its value is empty in a getObjectDataConfig array.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return null;
        }
        
        $response = $this->callSource($config, 'while trying to get object data for email and/or sms');

        // Loop through the specific properties we want to use from the response of this source call.
        foreach ($config['sourceProperties'] as $sourceProperty) {
            if (empty($response[$sourceProperty]) === true) {
                $this->logger->error("SourceProperty {$sourceProperty} does not exist or is empty.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
                return null;
            }

            $sourcePropertyValue = $response[$sourceProperty];
            
            // Check for recursion, make sure 'forParentProperties' is present before we can continue with recursion.
            if (empty($config['getObjectDataConfig']) === false && empty($config['getObjectDataConfig']['forParentProperties']) === true) {
                $this->logger->error("The key forParentProperties does not exist or its value is empty in a getObjectDataConfig->getObjectDataConfig array.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
                return null;
            }
            
            // Handle recursion, in the case we want to use the value from an object in a source to do another call on a source.
            if (empty($config['getObjectDataConfig']) === false
                && in_array($sourceProperty, $config['getObjectDataConfig']['forParentProperties']) === true
            ) {
                $config['getObjectDataConfig']['sourceEndpoint'] = $sourcePropertyValue;
                $filter                                    = $this->addSourceDataToFilter($filter, $config['getObjectDataConfig']);
            }

            // Make sure to update filter, replace {{property}} with the actual value.
            foreach ($filter as $key => $value) {
                $filter[$key] = str_replace("{{$sourceProperty}}", $sourcePropertyValue, $value);
            }
        }

        return $filter;

    }//end addSourceDataToFilter()


    /**
     * Does a $this->callService->call() on a $config['source'], using $this->data[$config['dataKey']] as url.
     *
     * @param array  $config  A config array containing the 'source' = reference to a source & 'dataKey' or 'url' = a property in the notification body where to find an url, 'dataKey' or just the 'url'.
     * @param string $message A message to add to any error logs created.
     *
     * @return array|null The decoded response from the call.
     */
    private function callSource(array $config, string $message): ?array
    {
        if ($this->validateConfigArray(['source', 'url|or|dataKey'], 'getObjectDataConfig (parent or sub"getObjectDataConfig" array)', $config,) === false) {
            return null;
        }
        
        $source = $this->resourceService->getSource($config['source'], 'open-catalogi/open-catalogi-bundle');
        if ($source === null) {
            return null;
        }

        if (empty($config['sourceEndpoint']) === true) {
            $config['sourceEndpoint'] = $this->data[$config['dataKey']];
        }

        $endpoint = str_replace($source->getLocation(), '', $config['sourceEndpoint']);
        
        $callConfig = [];
        if (empty($config['sourceQuery']) === false) {
            foreach ($config['sourceQuery'] as $key => $query) {
                // Todo: replace this if statement & trim() with some fancy regex.
                if (str_starts_with($query, '{{') === true && str_ends_with($query, '}}')) {
                    $query = trim($query, '{}');
                    $config['sourceQuery'][$key] = $this->data[$query];
                }
            }
            
            $callConfig['query'] = $config['sourceQuery'];
        }

        try {
            $response        = $this->callService->call($source, $endpoint, $callConfig);
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            $this->logger->error("Error when trying to call Source {$config['source']} $message: {$e->getMessage()}", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return null;
        }

        return $decodedResponse;

    }//end callSource()
    
    
    /**
     * Checks if given $config array has the $required keys and if not creates an error log.
     *
     * @param array $required The required keys. May contain a string containing "|or|" for an OneOf option.
     * @param string $message A string added to the error message. To specify where in the action configuration this config is defined.
     * @param array $config The config array to check if it contains the $required keys.
     *
     * @return bool True if $config array contains $required keys, else false.
     */
    private function validateConfigArray(array $required, string $message, array $config): bool
    {
        $missingKeys = [];
        
        foreach ($required as $requiredItem) {
            if (str_contains($requiredItem, '|or|')) {
                $oneOf = explode('|or|', $requiredItem);
                $oneOfFailed = true;
                
                foreach ($oneOf as $item) {
                    if (empty($config[$item]) === false && $oneOfFailed === false) {
                        $oneOfFailed = true;
                        break;
                    }
                    
                    if (empty($config[$item]) === false) {
                        $oneOfFailed = false;
                    }
                }
                
                if ($oneOfFailed === true) {
                    $missingKeys[] = $requiredItem;
                }
                
                continue;
            }
            
            if (empty($config[$requiredItem]) === true) {
                $missingKeys[] = $requiredItem;
            }
        }
        
        if (empty($missingKeys) === false) {
            $this->logger->error(
                "Action configuration $message is missing the following keys:".implode(',' ,$missingKeys),
                array_merge(
                    $config,
                    ['plugin' => 'common-gateway/customer-notifications-bundle']
                )
            );
            
            return false;
        }
        
        return true;
    }


    /**
     * Throws a Gateway event, used for throwing the email and/or SMS event.
     * Passes the $object array with the thrown event.
     *
     * @param array      $config The EmailConfig or SMSConfig.
     * @param array|null $object The object found or null.
     *
     * @return array Return data from the thrown event.
     */
    private function throwEvent(array $config, ?array $object): array
    {
        $event = new ActionEvent('commongateway.action.event', ($object ?? []), $config['throw']);

        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

        return $event->getData();

    }//end throwEvent()


    /**
     * If createObjectConfig has been configured in the Action->configuration.
     * This function will create an ObjectEntity using data from notification or other configured objects.
     *
     * @return array|null The Created Object. Or null.
     */
    private function handleObject(): ?array
    {
        if (empty($this->configuration['createObjectConfig']) === true) {
            return null;
        }

        $createObjectConfig = $this->configuration['createObjectConfig'];

        $schema = $this->resourceService->getSchema($createObjectConfig['schema'], 'open-catalogi/open-catalogi-bundle');
        if ($schema === null) {
            return null;
        }

        // todo...
        // Input for object creation?
        // Find (& do) Mapping
        // Create ObjectEntity
        return [];

    }//end handleObject()


}//end class
