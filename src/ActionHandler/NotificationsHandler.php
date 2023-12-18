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
                    'description' => 'Extra conditions for this action, makes it possible to check properties from an object in a Source outside the Gateway and use that data as conditions for running this action. All conditions in the "conditions" array are checked. Only properties/keys defined in a "sourceProperties" array can be used to check conditions for in the "conditions" array (see example, we check if "isEindstatus" = true, "isEindstatus" is present in a "sourceProperties" array). With "getObjectDataConfig" you can configure how a source will be called, to get the "sourceProperties" you need for your "conditions". "getObjectDataConfig" can be used recursively, if you do this you will need to add the array property "forParentProperties" containing the "sourceProperties" you would like to use to call another Source with (see example, "statustype" is a property on the source containing an url, it is present in the first "sourceProperties" array and in the "forParentProperties" after that). "getObjectDataConfig" must always have the properties: "source" (reference of the source to call), "sourceProperties" (properties to use from source response) & one of: "notificationProperty" (get url from the notification to call on a source), or "sourceEndpoint" (define a specific endpoint to call on a source) (, or "forParentProperties" in case of recursion), but it can also have the property: "sourceQuery" (query to use to call the source).',
                    'example'     => '{"getObjectDataConfig":{"source":"https://buren.nl/source/buren.zrc.source.json","notificationProperty":"body.resourceUrl","sourceProperties":["statustype"],"getObjectDataConfig":{"forParentProperties":["statustype"],"source":"https://buren.nl/source/buren.ztc.source.json","sourceProperties":["isEindstatus"]}},"conditions":{"isEindstatus":true}',
                    'nullable'    => true,
                ],
                'hoofdObjectSource'  => [
                    'type'        => 'string',
                    'description' => 'When this property is set the data from the notification hoofdObject will be available to use in your email & sms template. The given source (reference) will be called using the notification hoofdObject url and the return value will be passed through the thrown email/sms event. Only set this if you need it.',
                    'example'     => 'https://buren.nl/source/buren.zrc.source.json',
                    'nullable'    => true,
                ],
                'resourceUrlSource'  => [
                    'type'        => 'string',
                    'description' => 'When this property is set the data from the notification resourceUrl will be available to use in your email & sms template. The given source (reference) will be called using the notification resourceUrl url and the return value will be passed through the thrown email/sms event. Only set this if you need it.',
                    'example'     => 'https://buren.nl/source/buren.zrc.source.json',
                    'nullable'    => true,
                ],
                'emailConfig'        => [
                    'type'        => 'array',
                    'description' => 'This contains the configuration for sending an email after the notification has been received. If not present it will not be possible for emails to be sent. "getObjectDataConfig" can be used to configure how to find and add the data of one Common Gateway Object to the email Action data (and email message through the email template). "throw" is the event we should throw to trigger another EmailHandler action that will send the actual email. Basic details about how "getObjectDataConfig" works can be found in the description & example of the extraConditions property, please take a look at that first. Good to know: "source" = reference of the source to call. "sourceProperties" = this is the array with property names to get from the response of the source. "searchSchemas" = array with Schema references to use when searching an Object in de Gateway. "searchQuery" = query array to use when searching an Object in de Gateway, use {{sourcePropertyName}} here to insert the values got using "sourceProperties". Note that it also possible to use "getObjectDataConfig" recursively, see example of extraConditions for how this is done. Make sure to add the "forParentProperties" array and add the property to it that has an url in the value and can be used to call another (or the same) source again..',
                    'example'     => '{"getObjectDataConfig":{"source":"https://buren.nl/source/buren.zrc.source.json","sourceEndpoint":"/rollen","sourceQuery":{"zaak":"{{body.hoofdObject}}","omschrijvingGeneriek":"initiator"},"sourceProperties":["results.0.betrokkeneIdentificatie.inpBsn"],"searchSchemas":["https://commongateway.nl/klant.partij.schema.json"],"searchQuery":{"externeIdentificaties.partijIdentificator.objectId":"{{results.0.betrokkeneIdentificatie.inpBsn}}","externeIdentificaties.partijIdentificator.objecttype":"ingeschrevenpersonen"}},"throw":"notifications.zaak.created.email"}',
                    'nullable'    => true,
                ],
                'smsConfig'          => [
                    'type'        => 'array',
                    'description' => 'This contains the configuration for sending an SMS after the notification has been received. If not present it will not be possible for sms to be sent. "getObjectDataConfig" can be used to configure how to find and add the data of one Common Gateway Object to the SMS Action data (and SMS message through the SMS template), if set to "sameAsEmail" the same object (response from sources) as for email will be used (or the same configuration). "throw" is the event we should throw to trigger another SMSHandler action that will send the actual sms. For more details about how "getObjectDataConfig" works, please see the emailConfig property of this action->configuration.',
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
