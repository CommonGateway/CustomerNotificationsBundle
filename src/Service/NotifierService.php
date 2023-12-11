<?php

namespace CommonGateway\CustomerNotificationsBundle\Service;

use Adbar\Dot;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Author Wilco Louwerse <wilco@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class NotifierService
{

    private Environment $twig;

    private array $data;

    private array $configuration;


    public function __construct(
        Environment $twig
    ) {
        $this->twig = $twig;

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
    public function NotifyHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        $this->sendNotification();

        return $data;

    }//end EmailHandler()


    /**
     * Sends and email using an EmailTemplate with configuration for it. It is possible to use $object data in the email if configured right.
     *
     * @throws LoaderError
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     *
     * @return bool
     */
    private function sendNotification(): bool
    {
        // Create mailer with mailgun url
        $transport = Transport::fromDsn($this->configuration['serviceDNS']);
        $mailer    = new Notifier($transport);

        // Ready the email template with configured variables
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

        // If we have no sender, set sender to receiver
        if (!$sender) {
            $sender = $receiver;
        }

        // Create the email
        $email = (new Email())
            ->from($sender)
            ->to($receiver)
            // ->cc('cc@example.com')
            // ->bcc('bcc@example.com')
            // ->replyTo('fabien@example.com')
            // ->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->html($html)
            ->text($text);

        // Then we can handle some optional configuration
        if (empty($this->configuration['cc']) === false) {
            $email->cc($this->configuration['cc']);
        }

        if (empty($this->configuration['bcc']) === false) {
            $email->bcc($this->configuration['bcc']);
        }

        if (empty($this->configuration['replyTo']) === false) {
            $email->replyTo($this->configuration['replyTo']);
        }

        if (empty($this->configuration['priority']) === false) {
            $email->priority($this->configuration['priority']);
        }

        // todo: attachments
        // Send the email
        /*
         * @var Symfony\Component\Mailer\SentMessage $sentEmail
         */
        $mailer->send($email);

        return true;

    }//end sendEmail()


}//end class
