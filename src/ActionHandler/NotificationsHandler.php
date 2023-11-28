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
            'description' => 'This handler handles incoming notifications. Creating email and/or sms messages if configured to do so.',
            'required'    => [],
            'properties'  => [
                'extraConditions'    => [
                    'type'        => 'array',
                    'description' => 'Extra conditions for this action, makes it possible to check properties from an object in a Source outside the Gateway and use this as conditions. (In the example we check property "statustype" of the "body.resourceUrl" object from the notification. Using the source with reference "commongatewaySourceRef")',
                    'example'     => '{"body.resourceUrl":{"commongatewaySourceRef":"https://example.nl/source/example.brp.source.json","statustype":"https://finishedStatusTypeUrl"}}',
                    'nullable'    => true,
                ],
                'emailConfig'        => [
                    'type'        => 'array',
                    'description' => 'Configuration for sending an email after the notification has been received. If not present no email will be send. "useObjectEntityData" can be used to configure what entity should be used for getting and adding ObjectEntity data to the email. "throw" is the event we should throw to trigger another EmailHandler action that will send the actual email.',
                    'example'     => '{"useObjectEntityData":"https://example.nl/schema/example.partij.schema.json","throw":"notifications.zaak.created.email"}',
                    'nullable'    => true,
                ],
                'smsConfig'          => [
                    'type'        => 'array',
                    'description' => 'Configuration for sending an sms after the notification has been received. If not present no sms will be send. "useObjectEntityData" can be used to configure what entity should be used for getting and adding ObjectEntity data to the sms message. "throw" is the event we should throw to trigger another SMSHandler action that will send the actual sms.',
                    'example'     => '{"useObjectEntityData":"https://example.nl/schema/example.partij.schema.json","throw":"notifications.zaak.created.sms"}',
                    'nullable'    => true,
                ],
                'createObjectEntity' => [
                    'type'        => 'array',
                    'description' => 'Configuration for creating an ObjectEntity at the end of handling a Notification.',
                    'example'     => '{"entity":"https://example.nl/schema/example.contactMoment.schema.json","mapping":"https://example.nl/mapping/notifications.contactMoment.mapping.json"}',
                    'nullable'    => true,
                ],
            ],
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
     * @SuppressWarnings("unused") Handlers are strict implementations
     */
    public function run(array $data, array $configuration): array
    {
        return $this->notificationsService->notificationsHandler($data, $configuration);

    }//end run()


}//end class
