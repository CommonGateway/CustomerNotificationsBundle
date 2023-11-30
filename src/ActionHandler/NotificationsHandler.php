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
                    'description' => 'Configuration for sending an email after the notification has been received. If not present no email will be send. "getObjectDataConfig" can be used to configure how to find and add the data of one ObjectEntity to the email message. "throw" is the event we should throw to trigger another EmailHandler action that will send the actual email. Extra details about "getObjectDataConfig"; "notificationProperty" = property from the notification to use to call the source. "source" = reference of the source to call. "sourceProperties" = array with property names to get from the response of the source. "searchSchemas" = array with Schema references to use when searching an Object in de Gateway. "searchQuery" = query array to use when searching an Object in de Gateway, use {{sourcePropertyName}} her to insert the values got using "sourceProperties. Note that it also possible to use "getObjectDataConfig" recursively, see example for how this is done. Make sure to add the "forParentProperties" array and add the properties that have an url in the value and can be used to call another (or the same) source again..',
                    'example'     => '{"getObjectDataConfig":"getObjectDataConfig":{"notificationProperty": "body.hoofdObject","source": "source.ref","sourceProperties": ["rollen"],"getObjectDataConfig":{"source": "source.ref","sourceProperties": ["bsn"],"forParentProperties": ["rollen"]},"searchSchemas": ["Entity ref or uuid"],"searchQuery": {"propertyToFilterBSNOn": "{{bsn}}"}},"throw":"notifications.zaak.created.email"}',
                    'nullable'    => true,
                ],
                'smsConfig'          => [
                    'type'        => 'array',
                    'description' => 'Configuration for sending an sms after the notification has been received. If not present no sms will be send. "getObjectDataConfig" can be used to configure how to find and add the data of one ObjectEntity to the sms message, if set to "sameAsEmail" the same object as for email will be used. "throw" is the event we should throw to trigger another SMSHandler action that will send the actual sms. For more details about how "getObjectDataConfig" works, please see emailConfig.',
                    'example'     => '{"getObjectDataConfig":"sameAsEmail","throw":"notifications.zaak.created.sms"}',
                    'nullable'    => true,
                ],
                'createObjectConfig' => [
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
