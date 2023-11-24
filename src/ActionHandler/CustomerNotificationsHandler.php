<?php

namespace CommonGateway\CustomerNotificationsBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\CustomerNotificationsBundle\Service\CustomerNotificationsService;

/**
 * @todo
 *
 * @author Conduction.nl <info@conduction.nl>
 *
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */
class CustomerNotificationsHandler implements ActionHandlerInterface
{

    /**
     * The customer notifications service used by the handler
     *
     * @var CustomerNotificationsService
     */
    private CustomerNotificationsService $customerNotificationsService;


    /**
     * The constructor
     *
     * @param CustomerNotificationsService $customerNotificationsService The customer notifications service
     */
    public function __construct(CustomerNotificationsService $customerNotificationsService)
    {
        $this->customerNotificationsService = $customerNotificationsService;

    }//end __construct()


    /**
     * Returns the required configuration as a https://json-schema.org array.
     *
     * @return array The configuration that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://commongateway.nl/ActionHandler/CustomerNotificationsHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'CustomerNotifications ActionHandler',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     *
     * @SuppressWarnings("unused") Handlers ara strict implementations
     */
    public function run(array $data, array $configuration): array
    {
        return $this->customerNotificationsService->customerNotificationsHandler($data, $configuration);

    }//end run()


}//end class
