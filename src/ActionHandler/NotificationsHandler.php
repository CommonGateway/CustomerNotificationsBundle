<?php

namespace CommonGateway\CustomerNotificationsBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\CustomerNotificationsBundle\Service\NotificationsService;

/**
 * The Handler for handling notifications.
 *
 * @author Conduction.nl <info@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */
class NotificationsHandler implements ActionHandlerInterface
{

    /**
     * The notifications service used by the handler
     *
     * @var NotificationsService
     */
    private NotificationsService $notificationsService;


    /**
     * The constructor
     *
     * @param NotificationsService $notificationsService The notifications service
     */
    public function __construct(NotificationsService $notificationsService)
    {
        $this->notificationsService = $notificationsService;

    }//end __construct()


    /**
     * Returns the required configuration as a https://json-schema.org array.
     *
     * @return array The configuration that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://commongateway.nl/ActionHandler/NotificationsHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'Notifications ActionHandler',
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
        return $this->notificationsService->notificationsHandler($data, $configuration);

    }//end run()


}//end class
