<?php

namespace CommonGateway\CustomerNotificationsBundle\Service;

use Adbar\Dot;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Texter;
use Symfony\Component\Notifier\Transport;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Triggers sending a SMS via the Symfony Notifier.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class SmsService
{

    /**
     * The twig environment
     *
     * @var Environment
     */
    private Environment $twig;

    /**
     * The action data.
     *
     * @var array
     */
    private array $data;

    /**
     * The action configuration.
     *
     * @var array
     */
    private array $configuration;

    /**
     * The plugin logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    public function __construct(
        Environment $twig,
        LoggerInterface $pluginLogger
    ) {
        $this->twig   = $twig;
        $this->logger = $pluginLogger;

    }//end __construct()


    /**
     * Handles the sending of an email based on an event.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function SmsHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        $this->sendSms();

        return $data;

    }//end SmsHandler()


    /**
     * Sends and email using an EmailTemplate with configuration for it. It is possible to use $object data in the email if configured right.
     *
     * @throws LoaderError
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     *
     * @return bool
     */
    private function sendSms(): bool
    {
        // Ready the sms template with configured variables
        $variables = [];

        $dataDot = new Dot($this->data);
        foreach ($this->configuration['variables'] as $key => $variable) {
            // Response is the default used for creating emails after an /api endpoint has been called and returned a response.
            if ($dataDot->has('response'.$variable) === true) {
                $variables[$key] = $dataDot->get('response'.$variable);
                continue;
            }

            if ($dataDot->has($variable) === true) {
                $variables[$key] = $dataDot->get($variable);
                continue;
            }

            if ((str_contains($variable, '{%') === true && str_contains($variable, '%}') === true)
                || (str_contains($variable, '{{') === true && str_contains($variable, '}}') === true)
            ) {
                $variables[$key] = $this->twig->createTemplate($variable)->render($variables);
            }
        }

        // Render the template
        $html = $this->twig->createTemplate(base64_decode($this->configuration['template']))->render($variables);
        $text = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html), '\n');

        // Lets allow the use of values from the object Created/Updated with {attributeName.attributeName} in the these^ strings.
        $subject  = $this->twig->createTemplate($this->configuration['subject'])->render($variables);
        $receiver = $this->twig->createTemplate($this->configuration['receiver'])->render($variables);
        $sender   = $this->twig->createTemplate($this->configuration['sender'])->render($variables);

        // If we have no sender, do not send the SMS
        if (!$sender) {
            $this->logger->error('No sender set, could not send SMS', ['plugin' => 'common-gateway/customer-notifications-bundle']);
            return false;
        }

        // Create texter with service DSN
        $transport = Transport::fromDsn($this->configuration['serviceDNS'].'?from='.$sender);
        $texter    = new Texter($transport);

        $sms = new SmsMessage(
            $receiver,
            $text
        );

        // Send the email
        /*
         * @var Symfony\Component\Mailer\SentMessage $sentEmail
         */
        $texter->send($sms);

        return true;

    }//end sendSms()


}//end class
