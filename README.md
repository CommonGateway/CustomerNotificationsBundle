# Over de berichten service [![Codacy Badge](https://app.codacy.com/project/badge/Grade/980ea2efc85a427ea909518f29506ff6)](https://app.codacy.com/gh/CommonGateway/CustomerNotificationsBundle/dashboard?utm_source=gh\&utm_medium=referral\&utm_content=\&utm_campaign=Badge_grade)

## Hét echte omnichannel-component

De Berichtenservice positioneert zich als het ultieme omnichannel-component, door een uitzonderlijke veelzijdigheid in communicatiekanalen aan te bieden. Deze service gaat verder dan alleen SMS, e-mail, en de Mijn Overheid berichtenbox; het omarmt ook de kracht van sociale media en messaging-apps door ondersteuning te bieden voor het versturen van berichten via WhatsApp, Facebook, en LinkedIn. Deze aanpak zorgt ervoor dat overheidsinstanties een brede en gevarieerde doelgroep kunnen bereiken, ongeacht hun voorkeursplatform voor communicatie.

Bovendien breidt de Berichtenservice zijn horizon uit door integratie met chatdiensten zoals Slack, waardoor het een ideaal platform wordt voor zowel interne als externe communicatie. Deze toevoeging versterkt de brug tussen overheidsinstanties en inwoners, maar ook binnen teams en afdelingen, door een gestroomlijnde en efficiënte communicatieomgeving te bieden.

Daarnaast introduceert het ondersteuning voor pushberichten, die direct in browsers of op mobiele telefoons van gebruikers kunnen worden afgeleverd. Deze technologie stelt overheidsorganisaties in staat om real-time updates en belangrijke informatie te versturen, wat de betrokkenheid en het bereik van hun communicatie verder vergroot.

De integratie met Postex voor het versturen van fysieke post rondt het omnichannel aanbod af, door een naadloze overgang tussen digitale en traditionele communicatiemethoden te bieden. Deze omvattende aanpak verzekert dat elke inwoner effectief bereikt kan worden, of ze nu de voorkeur geven aan digitale media, traditionele post, sociale platformen, chatdiensten, of directe meldingen. Zo wordt de toegankelijkheid en doeltreffendheid van overheidscommunicatie op een innovatieve manier verbeterd, passend bij het digitale tijdperk.

## Brede ondersteuning van aanbieders

De Berichtenservice van de CustomerInteractionBundle maakt gebruik van de Symfony Notifier-component, een flexibel en krachtig systeem dat is ontworpen om applicatieontwikkelaars in staat te stellen notificaties te verzenden via een veelheid aan kanalen. Door deze basis kunnen ontwikkelaars gemakkelijk integreren met een breed scala aan externe diensten, wat de Berichtenservice tot een werkelijk omnichannel communicatieplatform maakt. Symfony Notifier ondersteunt niet alleen traditionele communicatiemethoden zoals e-mail en SMS, maar ook moderne messaging-apps en sociale netwerken, en biedt zelfs de mogelijkheid voor het versturen van pushberichten naar browsers en mobiele apparaten.

De flexibiliteit van Symfony Notifier ligt in zijn ontwerp, waardoor ontwikkelaars eenvoudig nieuwe "transport" kanalen kunnen toevoegen en configureren, afhankelijk van de behoeften van de applicatie. Dit maakt het een ideale keuze voor overheidsinstanties die op zoek zijn naar een oplossing die kan groeien en zich aanpassen aan de veranderende communicatievoorkeuren van hun inwoners.

## Automatisch berichten sturen aan de hand van notificaties

### Installation with the Common Gateway admin user-interface

Once a bundle is set up correctly (like this repository), the Common Gateway can discover the bundle without additional configuration. Head to the `Plugins` tab to search, select and install plugins.

#### Installing with PHP commands

To execute the following command, you will need [Composer](https://getcomposer.org/download/) or a dockerized installation that already has PHP and Composer.

The Composer method in the terminal and root folder:

> for the installation of the plugin

`$composer require common-gateway/customer-notifications-bundle:dev-main`

> for the installation of schemas

`$php bin/console commongateway:install common-gateway/customer-notifications-bundle`

The dockerized method in the terminal and root folder:

> for the installation of the plugin

`$docker-compose exec php composer require common-gateway/customer-notifications-bundle:dev-main`

> for the installation of schemas

`$docker-compose exec php bin/console commongateway:install common-gateway/customer-notifications-bundle`

## Configuration for emails and/or SMS

This bundle can be used for (only) sending emails or SMS messages by creating email or SMS-specific Common Gateway Actions.
To do this you will need to create a Common Gateway Action for the `EmailHandler` or `SmsHandler` [ActionHandler](https://commongateway.github.io/CoreBundle/pages/Features/Action_handlers) respectively.

### How to create an email or SMS Action

Most of the configuration for these Actions can be configured by using the Gateway admin UI, except for one configuration property: `variables`.
If you want/need to use or change this Action configuration property: `variables`, we recommend including your Action directly in the installation files of the bundle ([Common Gateway plugin](https://commongateway.github.io/CoreBundle/pages/Features/Plugins)) you are working with or use an API-platform tool like Postman to directly POST (, PATCH or UPDATE) your Action on the Common Gateway you are working with.

So now that you know how to create an email and/or SMS Action, you should also know and understand the requirements of the configuration for one of these (/any) Actions:

* A `name`, your Action is going to need a name.
* A `reference`, each Action needs a unique reference URL starting with `https://{your-domain}/action/{short-name-for-your-bundle}` and ending with `.action.json`, something like: `"https://commongateway.nl/action/notifications.ZaakCreatedEmailAction.action.json"`
* Each Action needs to listen to one or more [Common Gateway events](https://commongateway.github.io/CoreBundle/pages/Features/Events) you can add this to the `listens` array of your Action.
* Some [JsonLogic](https://jsonlogic.com/) `conditions` that will be compared to the Action data, these conditions determine when your Action should be triggered. Use `{"==": [1, 1]}` for 'always true'.
* The `class`, this should be `"CommonGateway\CustomerNotificationsBundle\ActionHandler\EmailHandler"` or `"CommonGateway\CustomerNotificationsBundle\ActionHandler\SmsHandler"` for this type of Action.
* A `configuration` array containing specific configuration for your email or SMS Action, is probably the most complex thing in this list, so because of that [below](#email-and-sms-action-configuration) this summary/list you will find a more detailed explanation.

### Email and SMS Action configuration

The email and SMS Action configurations are very similar, here is a list of configuration properties that can be used for email and SMS Actions:

* \[**Required**] `serviceDNS` The DNS of the [mail](https://symfony.com/doc/6.2/mailer.html) or [sms](https://symfony.com/doc/current/notifier.html#sms-channel) provider.
* \[**Required**] `template` The template of your email or sms. This should be a base64 encoded [twig template](https://symfony.com/doc/current/templates.html#twig-templating-language). For (not base64 encoded) examples see the [root/src/EmailTemplates folder](https://github.com/CommonGateway/CustomerNotificationsBundle/tree/main/src/EmailTemplates).
* \[**Required**] `sender` The sender. Email for email Action. 'from' string for SMS (example: Gemeente%20Mordor). It is possible to use twig here to add one or multiple variables from the `variables` array.
* \[**Required**] `receiver` The receiver. Email for email Action. Phone number for SMS. It is possible to use twig here to add a variable from the `variables` array.
* `variables` The variables array, with this you can configure which variables (keys of the `variables` array) can be used in your template and fill these values (values of the `variables` array) by using a dot notation reference to a property in the Action data.

> **Note:**
> For examples of SMS Actions see all Actions in the [root/Installation/Action folder](https://github.com/commonGateway/customernotificationsBundle/tree/main/Installation/Action) that use the ActionHandler (class) `CommonGateway\CustomerNotificationsBundle\ActionHandler\SMSHandler`.

> **Note:**
> It is possible to use twig to add variables from the `variables` array in another value in the `variables` array, as long as the variables used are defined earlier/higher in the `variables` array. (See variables.body in [this example](https://github.com/CommonGateway/CustomerNotificationsBundle/blob/main/Installation/Action/notifications.ZaakCreatedEmailAction.action.json)).

#### Email Action specific configuration

The email Action configuration has a few more properties you can use than with the SMS Action configuration.
For all these properties it is possible to use twig to add one or multiple variables from the `variables` array:

* \[**Required**] `subject` The subject of the email.
* `cc` Carbon copy, email boxes that should receive a copy of this mail.
* `bcc` Blind carbon copy, people that should receive a copy without other recipients knowing.
* `replyTo` The address the receiver should reply to, only provide this if it differs from the sender's address.
* `priority` An optional priority for the email.

> **Note:**
> For examples of email Actions see all Actions in the [root/Installation/Action folder](https://github.com/commonGateway/customernotificationsBundle/tree/main/Installation/Action) that use the ActionHandler (class) `CommonGateway\CustomerNotificationsBundle\ActionHandler\EmailHandler`.

## Configuration for notifications

It is also possible to trigger the email and/or SMS Actions you configured through notifications. The CustomerNotificiationsBundle adds a new Common Gateway endpoint that can be used to send your [ZGW notifications](https://vng-realisatie.github.io/gemma-zaken/themas/achtergronddocumentatie/notificaties) to: `{{gateway-domain}}/api/notifications`

All notifications sent to this endpoint will trigger a [Common Gateway event](https://commongateway.github.io/CoreBundle/pages/Features/Events): \
`notifications.notification.created`

And by creating a Common Gateway Action using the `NotificationsHandler` [ActionHandler](https://commongateway.github.io/CoreBundle/pages/Features/Action_handlers) you can configure which notifications should trigger a new [Common Gateway event](https://commongateway.github.io/CoreBundle/pages/Features/Events) for sending an email or sms.

### How to create a notification Action

Normally you can create Actions through the Gateway admin UI, but the notification Action has some complex configuration that can currently not be configured with the Gateway UI.
Because of this it is recommended to include your Action directly in the installation files of the bundle ([Common Gateway plugin](https://commongateway.github.io/CoreBundle/pages/Features/Plugins)) you are working with or use an API-platform tool like postman to directly POST (, PATCH or UPDATE) your Action on the Common Gateway you are working with.

Now that you know how to create a notification Action, you should also know and understand the requirements of the configuration for a (notification) Action:

* A `name`, your Action is going to need a name.
* A `reference`, each Action needs a unique reference URL starting with `https://{your-domain}/action/` and ending with `.action.json`, something like: `"https://commongateway.nl/action/notifications.ZaakCreatedAction.action.json"`
* Each Action needs to listen to one or more [Common Gateway events](https://commongateway.github.io/CoreBundle/pages/Features/Events) you can add this to the `listens` array of your Action. This will most likely be `["notifications.notification.created"]` if you are working with [ZGW notifications](https://vng-realisatie.github.io/gemma-zaken/themas/achtergronddocumentatie/notificaties).
* Some [JsonLogic](https://jsonlogic.com/) `conditions`, these conditions determine when your notification Action should be triggered. When it triggers it will throw the event that will trigger the Action that sends an email or SMS. [Below](#notification-action-conditions) this summary/list you will find an example.
* The `class`, this should be `"CommonGateway\CustomerNotificationsBundle\ActionHandler\NotificationsHandler"` for your notification Action.
* A `configuration` array containing specific configuration for getting and passing information to your email and/or SMS Actions, probably the most complex thing in this list, so because of that [below](#notification-action-configuration) this summary/list you will find a more detailed explanation and example.

### Notification Action conditions

To only send an email or SMS for a specific type of notification you can use the Action `conditions` in combination with the Action configuration to only make your Action trigger for the notifications you want.
Action conditions use [JsonLogic](https://jsonlogic.com/) to compare the Action data with your conditions.

Here is an example of the conditions for a 'case created' / 'zaak aangemaakt' notification Action:

```json
{
    "and": [
        {
            "in": [
                "https://open-zaak.test.buren.opengem.nl/zaken/api/v1",
                {
                    "var": "body.kanaal"
                }
            ]
        },
        {
            "==": [
                {
                    "var": "body.kanaal"
                },
                "zaken"
            ]
        },
        {
            "==": [
                {
                    "var": "body.resource"
                },
                "zaak"
            ]
        },
        {
            "==": [
                {
                    "var": "body.actie"
                },
                "create"
            ]
        }
    ]
}
```

> **Note:**
> In these Action conditions you can use most properties of the Request through the Action data, so besides checking body.bodyProperty you could for example check method=POST as well.

> **Note:**
> For more examples see all Actions in the [root/Installation/Action folder](https://github.com/commonGateway/customernotificationsBundle/tree/main/Installation/Action) that use the ActionHandler (class) `CommonGateway\CustomerNotificationsBundle\ActionHandler\NotificationsHandler`.

In some cases, you want to check a little bit more than is possible with only the Action conditions.
Such as getting and checking information from the ZGW notification hoofdObject or resourceUrl objects.
To learn more about this please check the Action configuration `extraConditions` [below](#extraconditions).

### Notification Action configuration

The configuration of your notification Action can be used for a few things, we will go into detail here on what you can configure.
(The same information should be provided in the `src/ActionHandler/NotificationsHandler.php` file itself).
Most properties are not required to add, please consider what you need for your use case and add the required configuration for that.
If you are missing any required fields you will find error logs about this in the Gateway UI while testing.

Here is a very complex and extensive example of the Action configuration for a 'case status is finished' / 'zaak status is eindstatus' notification:

```json
{
    "extraConditions": {
        "getObjectDataConfig": {
            "source": "https://buren.nl/source/buren.zrc.source.json",
            "notificationProperty": "body.resourceUrl",
            "sourceProperties": ["statustype"],
            "getObjectDataConfig": {
                "forParentProperties": ["statustype"],
                "source": "https://buren.nl/source/buren.ztc.source.json",
                "sourceProperties": ["isEindstatus"]
            }
        },
        "conditions": {
            "isEindstatus": true
        }
    },
    "hoofdObjectSource": "https://buren.nl/source/buren.zrc.source.json",
    "emailConfig": {
        "getObjectDataConfig": {
            "source": "https://buren.nl/source/buren.zrc.source.json",
            "sourceEndpoint": "/rollen",
            "sourceQuery": {
                "zaak": "{{body.hoofdObject}}",
                "omschrijvingGeneriek": "initiator"
            },
            "sourceProperties": ["results.0.betrokkeneIdentificatie.inpBsn"],
            "searchSchemas": ["https://commongateway.nl/klant.partij.schema.json"],
            "searchQuery": {
                "externeIdentificaties.partijIdentificator.objectId": "{{results.0.betrokkeneIdentificatie.inpBsn}}",
                "externeIdentificaties.partijIdentificator.objecttype": "ingeschrevenpersonen"
            }
        },
        "objectConditions": {
            "embedded.voorkeurskanaal.soortDigitaalAdres": "emailadres"
        },
        "throw": "notifications.zaak.status.finished.email"
    },
    "smsConfig": {
        "getObjectDataConfig": "sameAsEmail",
        "objectConditions": {
            "embedded.voorkeurskanaal.soortDigitaalAdres": "telefoonnummer"
        },
        "throw": "notifications.zaak.status.finished.sms"
    },
    "createObjectConfig": {
        "schema": "https://commongateway.nl/klant.klantcontact.schema.json",
        "mapping": "Mapping ref or uuid"
    }
}
```

> **Note:**
> For more examples see all Actions in the [root/Installation/Action folder](https://github.com/commonGateway/customernotificationsBundle/tree/main/Installation/Action) that use the ActionHandler (class) `CommonGateway\CustomerNotificationsBundle\ActionHandler\NotificationsHandler`.

#### extraConditions

The extra conditions for this action, make it possible to check properties from an object in a Source outside the Gateway and use that data as extra conditions for running this action.
All conditions in the `"conditions"` array are checked.

Only properties/keys defined in a `sourceProperties` array can be used to check conditions in the `"conditions"` array.
See the example [above](#notification-action-configuration), we check if `isEindstatus = true`, `isEindstatus` is present in a `sourceProperties` array.

With `getObjectDataConfig` you can configure how a source will be called, to get the `sourceProperties` you need for your `"conditions"`.
`getObjectDataConfig` can be used recursively, if you do this you will need to add the array property `forParentProperties` containing the `sourceProperties` you would like to use to call another Source with.
See the example [above](#notification-action-configuration), `"statustype"` is a property on the source containing a url, it is present in the first `sourceProperties` array and the `forParentProperties` after that.

`getObjectDataConfig` must always have the properties:

* `source` Reference of the source to call.
* `sourceProperties` Properties to use from source response.
* & one of:
  * `notificationProperty` Get URL from the notification to call on a source.
  * `sourceEndpoint` Define a specific endpoint to call on a source.
  * `forParentProperties` In case of recursion add the sourceProperty name here, that has an URL in the value, so that can be used to call another (or the same) source.

But `getObjectDataConfig` can also have the property:

* `sourceQuery` Query to use to call the source.

#### hoofdObjectSource

When this property is set the data from the notification hoofdObject will be available in your email and SMS template.
The given source (reference) will be called using the notification hoofdObject URL and the return value will be passed through the thrown email/SMS event. \
Only set this if you need it.

#### resourceUrlSource

Does exactly the same as hoofdObjectSource but for the notification resourceUrl instead. (not present in the example [above](#notification-action-configuration))\
Only set this if you need it.

#### emailConfig

This contains the configuration for sending an email after the notification has been received.
If not present it will not be possible for emails to be sent.

* `getObjectDataConfig` can be used to configure how to find and add the data of one Common Gateway Object to the email Action data (and email message through the email template).
* `objectConditions` can be used to add some final conditions to check using the object found with the `getObjectDataConfig`. If these conditions fail the email will not be sent. (as long as `objectConditions` is not empty, the email will also not be sent if no object was found with `getObjectDataConfig`).
* `throw` is the event we should throw to trigger another [EmailHandler action](#configuration-for-emails-andor-sms) that will send the actual email.

Basic details about how `getObjectDataConfig` works can be found in the description of the [extraConditions](#extraconditions) property, please take a look at that first.
Good to know & emailConfig specific properties:

* `source` Reference of the source to call.
* `sourceProperties` This is the array with property names to get from the response of the source.
* `searchSchemas` Array with Schema references to use when searching an Object in de Gateway.
* `searchQuery` Query array to use when searching an Object in de Gateway, use {{sourcePropertyName}} here to insert the values got using `sourceProperties`. See example [above](#notification-action-configuration).

> **Note:**
> that it is also possible to use `getObjectDataConfig` recursively, see [extraConditions](#extraconditions) for how this is done.

#### smsConfig

This contains the configuration for sending an SMS after the notification has been received.
If not present it will not be possible for sms to be sent.

* `getObjectDataConfig` can be used to configure how to find and add the data of one Common Gateway Object to the SMS Action data (and SMS message through the SMS template), if set to `"sameAsEmail"` the same object (response from sources) as for email will be used (or the same configuration).
* `objectConditions` can be used to add some final conditions to check using the object found with the `getObjectDataConfig`. If these conditions fail the SMS will not be sent. (as long as `objectConditions` is not empty, the SMS will also not be sent if no object was found with `getObjectDataConfig`).
* `throw` is the event we should throw to trigger another [SMSHandler action](#configuration-for-emails-andor-sms) that will send the actual SMS.

For more details about how `getObjectDataConfig` works, please see the [emailConfig property](#emailconfig).

> **Note:**
> smsConfig works exactly the same as the emailConfig except for the use of `"sameAsEmail"`.

#### createObjectConfig

This currently doesn't do anything, this is a work in progress. \
When this is finished it can however be used to create specific Common Gateway Objects at the end of handling a notification. To create for example a 'klantcontact' Object after the email and/or SMS has been sent.
