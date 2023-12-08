<?php

namespace CommonGateway\CustomerNotificationsBundle\Service;

use Adbar\Dot;
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
    private array $configuration= [];
    
    /**
     * Data array.
     *
     * @var array
     */
    private array $data = [];
    
    /**
     * Data array as an Adbar\Dot object.
     *
     * @var Dot|null
     */
    private ?Dot $dataDot = null;

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
        $this->dataDot       = new Dot($this->data);
        $this->configuration = $configuration;
        
        $this->logger->debug("NotificationsBundler -> NotificationsService -> notificationsHandler()", ['plugin' => 'common-gateway/customer-notifications-bundle']);
        
        $extraConditions = $this->handleExtraConditions();
        if ($extraConditions !== []) {
            $response         = ['Message' => "Failed to match the following extraConditions", "ExtraConditions" => $extraConditions];
            $data['response'] = new Response(\Safe\json_encode($response), 200, ['Content-type' => 'application/json']);
            
            return $data;
        }

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
        $data['response'] = new Response(\Safe\json_encode($response), 200, ['Content-type' => 'application/json']);

        return $data;

    }//end notificationsHandler()
    
    
    /**
     * If the action configuration contains extraConditions, check if these conditions are met.
     *
     * @return array Empty array if conditions are met, else an array with the failed conditions or an error message.
     */
    private function handleExtraConditions(): array
    {
        // If there are no extra conditions return true.
        if (empty($this->configuration['extraConditions']) === true) {
            return [];
        }
        
        $extraConditions = $this->configuration['extraConditions'];
        
        if ($this->validateConfigArray(['getObjectDataConfig', 'conditions'], 'extraConditions', $extraConditions) === false) {
            return ['Message' => 'Action->configuration should have the following keys in extraConditions', "extraConditions" => ['getObjectDataConfig', 'conditions']];
        }
        
        $checkedConditions = $this->handleObjectDataConfig(
            $extraConditions['conditions'],
            $extraConditions['getObjectDataConfig'],
            'extraConditions'
        );
        
        // We unset all conditions that have been met, array should be empty at this point.
        if (empty($checkedConditions) === false) {
            return $checkedConditions;
        }
        
        return [];
        
    }//end handleExtraConditions()


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
            if ($smsConfig['getObjectDataConfig'] === 'sameAsEmail') {
                if (empty($this->configuration['emailConfig']['getObjectDataConfig']['SMSSameAsEmail']) === false) {
                    $object = $this->configuration['emailConfig']['getObjectDataConfig']['SMSSameAsEmail'];
                } elseif (empty($this->configuration['emailConfig']['getObjectDataConfig']) === false) {
                    $smsConfig['getObjectDataConfig'] = $this->configuration['emailConfig']['getObjectDataConfig'];
                }
            }
            
            if ($smsConfig['getObjectDataConfig'] !== 'sameAsEmail') {
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
        if ($this->validateConfigArray(['notificationProperty|or|sourceEndpoint', 'searchSchemas', 'searchQuery'], 'email or sms getObjectDataConfig', $config,) === false) {
            return null;
        }
        
        $filter = $config['searchQuery'];
        
        $filter = $this->handleObjectDataConfig($filter, $config, 'emailOrSMSConfig');
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
        
        // Deal with MongoDBDocuments...
        return \Safe\json_decode(\Safe\json_encode($objects['results'][0]), true);

    }//end getObject()


    /**
     * Depending on the $variant used this function behaves differently. But for both variants this function uses data from an getObjectDataConfig array to get data from sources outside the gateway
     * 'extraConditions' = This function will check the conditions from the given $conditions array and removes all conditions that are met.
     * 'emailOrSMSConfig' = This function will update the given $filter array so that it contains the correct filter values.
     * If configured correctly to do so this is a function with recursion.
     *
     * @param array $conditionsOrFilter The Conditions to check for extraConditions. Or the filter array to update and add data gathered data from one or more Sources to.
     * @param array $config   The 'getObjectDataConfig' config (For ExtraConditions, EmailConfig, SMSConfig, or config used for recursion).
     * @param string $variant Enum = ['extraConditions', 'emailOrSMSConfig']. Depending on which type of action->configuration (ObjectDataConfig) we are handling.
     *
     * @return array|null The updated $conditions array. Or the updated $filter array.
     */
    private function handleObjectDataConfig(array $conditionsOrFilter, array $config, string $variant): ?array
    {
        if (empty($config['sourceProperties']) === true) {
            $this->logger->error("The key sourceProperties does not exist or its value is empty in a getObjectDataConfig array.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return null;
        }
        
        $response = $this->callSource($config, ["getObjectDataConfig (parent or sub'getObjectDataConfig' array)", 'while trying to get object data for email and/or sms']);
        $responseDot = new Dot($response);

        // Loop through the specific properties we want to use from the response of this source call.
        foreach ($config['sourceProperties'] as $sourceProperty) {
            if ($responseDot->has($sourceProperty) === false || empty($responseDot->get($sourceProperty)) === true) {
                $this->logger->error("SourceProperty {$sourceProperty} does not exist or is empty.", ['plugin' => 'common-gateway/customer-notifications-bundle']);
                return null;
            }

            $sourcePropertyValue = $responseDot->get($sourceProperty);
            
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
                $conditionsOrFilter = $this->handleObjectDataConfig($conditionsOrFilter, $config['getObjectDataConfig'], $variant);
            }
            
            if ($variant === 'extraConditions') {
                $conditionsOrFilter = $this->checkExtraConditions($conditionsOrFilter, $sourceProperty, $sourcePropertyValue);
            }
            
            if ($variant === 'emailOrSMSConfig') {
                $conditionsOrFilter = $this->addSourceDataToFilter($conditionsOrFilter, $sourceProperty, $sourcePropertyValue);
            }
        }

        return $conditionsOrFilter;

    }//end addSourceDataToFilter()
    
    
    /**
     * Handles logic for handleObjectDataConfig() variant 'extraConditions'.
     * This function checks if given $conditions array contains the $sourceProperty key and if the value for this conditions matches the given $sourcePropertyValue.
     * Will unset $sourceProperty from conditions if the condition has been met.
     *
     * @param array $conditions The conditions that have to be met.
     * @param string $sourceProperty The source property to check.
     * @param mixed $sourcePropertyValue The value for this key to compare.
     *
     * @return array The updated or unchanged $conditions array.
     */
    private function checkExtraConditions(array $conditions, string $sourceProperty, $sourcePropertyValue): array
    {
        // If this sourceProperty doesn't need to be checked.
        if (isset($conditions[$sourceProperty]) === false) {
            return $conditions;
        }
        
        // If conditions isn't met
        if ($conditions[$sourceProperty] != $sourcePropertyValue) {
            $this->logger->debug("ExtraCondition $sourceProperty doesn't match expected condition.", ['expectedCondition' => $conditions[$sourceProperty],'sourcePropertyValue' => $sourcePropertyValue, 'plugin' => 'common-gateway/customer-notifications-bundle']);
            return $conditions;
        }
        
        // Make sure to unset from $conditions array so we later know conditions where met.
        unset($conditions[$sourceProperty]);
        
        return $conditions;
    }
    
    /**
     * Handles logic for handleObjectDataConfig() variant 'emailOrSMSConfig'.
     * This function will check if given $sourceProperty key is present in the given $filter array.
     * Overwrites every {{$sourceProperty}} with the given $sourcePropertyValue.
     *
     * @param array $filter The filter array to update.
     * @param string $sourceProperty The source property to check.
     * @param mixed $sourcePropertyValue The value of the $sourceProperty key.
     *
     * @return array The updated $filter array.
     */
    private function addSourceDataToFilter(array $filter, string $sourceProperty, $sourcePropertyValue): array
    {
        // Make sure to update filter, replace {{property}} with the actual value.
        foreach ($filter as $key => $value) {
            $filter[$key] = str_replace("{{{$sourceProperty}}}", $sourcePropertyValue, $value);
        }
        
        return $filter;
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
        $eventData['notification']['body'] = $this->data['body'];
        
        if (empty($object) === false) {
            $eventData['object'] = $object;
        }
        
        if (empty($this->configuration['hoofdObjectSource']) === false) {
            $eventData['hoofdObject'] = $this->eventAddSourceData($this->configuration['hoofdObjectSource'], 'hoofdObject');
        }
        
        if (empty($this->configuration['resourceUrlSource']) === false) {
            $eventData['resourceUrl'] = $this->eventAddSourceData($this->configuration['resourceUrlSource'], 'resourceUrl');
        }
        
        $event = new ActionEvent('commongateway.action.event', $eventData, $config['throw']);
        
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');
        
        return $event->getData();
        
    }//end throwEvent()
    
    
    /**
     * Uses given $sourceRef & $type input to callSource() and get data for an object from a Source. (specifically for the hoofdObject url or resourceUrl).
     * Todo: We might need to add some recursion here, in order to do calls on Sources for subobject urls. This would also mean we need to update configuration and Handler configuration descriptions.
     *
     * @param string $sourceRef A reference of a source or the string 'sameAsEmail'. From the action->configuration[emailConfig/smsConfig][hoofdObjectSource/resourceUrlSource].
     * @param string $type One of: hoofdObject or resourceUrl.
     *
     * @return array|null The object from the source. Or null.
     */
    private function eventAddSourceData(string $sourceRef, string $type): ?array
    {
        if (empty($this->configuration['emailConfig'][$type.'Source']['SMSSameAsEmail']) === false) {
            return $this->configuration['emailConfig'][$type.'Source']['SMSSameAsEmail'];
        }
        
        $config = [
            'source' => $sourceRef,
            'notificationProperty' => "body.$type"
        ];
        $object = $this->callSource($config, ['emailConfig or smsConfig', "while trying to get $type object for email and/or sms"]);
        
        $this->configuration['emailConfig'][$type.'Source']['SMSSameAsEmail'] = $object;
        
        return $object;
    }
    

    /**
     * Does a $this->callService->call() on a $config['source'], using $this->data[$config['notificationProperty']] as sourceEndpoint.
     *
     * @param array  $config  A config array containing the 'source' = reference to a source & 'notificationProperty' or 'sourceEndpoint' = a property in the notification body where to find an url, 'notificationProperty' or just the 'sourceEndpoint'.
     * @param array $messages Messages to add to any error logs created. [0] will specifically be passed to validateConfigArray() function. [1] will be used for all logs created in this function.
     *
     * @return array|null The decoded response from the call.
     */
    private function callSource(array $config, array $messages): ?array
    {
        if ($this->validateConfigArray(['source', 'sourceEndpoint|or|notificationProperty'], "$messages[0] ($messages[1])", $config,) === false) {
            return null;
        }
        
        $source = $this->resourceService->getSource($config['source'], 'common-gateway/customer-notifications-bundle');
        if ($source === null) {
            return null;
        }

        if (empty($config['sourceEndpoint']) === true) {
            $config['sourceEndpoint'] = $this->dataDot->get($config['notificationProperty']);
        }

        $endpoint = str_replace($source->getLocation(), '', $config['sourceEndpoint']);
        
        $callConfig = [];
        if (empty($config['sourceQuery']) === false) {
            foreach ($config['sourceQuery'] as $key => $query) {
                // Todo: replace this if statement & trim() with some fancy regex.
                if (str_starts_with($query, '{{') === true && str_ends_with($query, '}}')) {
                    $query = trim($query, '{}');
                    $config['sourceQuery'][$key] = $this->dataDot->get($query);
                }
            }
            
            $callConfig['query'] = $config['sourceQuery'];
        }

        try {
            $response        = $this->callService->call($source, $endpoint, 'GET', $callConfig);
            $decodedResponse = $this->callService->decodeResponse($source, $response);
        } catch (Exception $e) {
            $this->logger->error("Error when trying to call Source {$config['source']} $messages[1]: {$e->getMessage()}", ['plugin' => 'common-gateway/customer-notifications-bundle']);
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

        $schema = $this->resourceService->getSchema($createObjectConfig['schema'], 'common-gateway/customer-notifications-bundle');
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
