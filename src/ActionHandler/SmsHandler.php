<?php

namespace CommonGateway\CustomerNotificationsBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\CustomerNotificationsBundle\Service\SmsService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SmsHandler implements ActionHandlerInterface
{

    private SmsService $smsService;


    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;

    }//end __construct()


    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://commongateway.nl/ActionHandler/SmsHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'SmsHandler',
            'required'   => [
                'ServiceDNS',
                'template',
                'sender',
                'receiver',
                'subject',
            ],
            'properties' => [
                'serviceDNS' => [
                    'type'        => 'string',
                    'description' => 'The DNS of the sms provider, see https://symfony.com/doc/current/notifier.html#sms-channel for details',
                    'example'     => 'native://default',
                    'required'    => true,
                ],
                'template'   => [
                    'type'        => 'string',
                    'description' => 'The actual sms template, should be a base64 encoded twig template',
                    'example'     => 'eyUgaWYgYm9keXxkZWZhdWx0ICV9e3sgYm9keSB8IG5sMmJyIH19eyUgZW5kaWYgJX0K',
                    'required'    => true,
                ],
                'variables'  => [
                    'type'        => 'array',
                    'description' => 'The variables supported by this template (can contain default values)',
                    'nullable'    => true,
                ],
                'sender'     => [
                    'type'        => 'string',
                    'description' => 'The sender of the sms',
                    'example'     => 'Gemeente%20Mordor',
                    'required'    => true,
                ],
                'receiver'   => [
                    'type'        => 'string',
                    'description' => 'The receiver of the sms',
                    'example'     => '+31612345678',
                    'required'    => true,
                ],
            ],
        ];

    }//end getConfiguration()


    /**
     * This function runs the sms service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->smsService->smsHandler($data, $configuration);

    }//end run()


}//end class
