# CustomerNotificationsBundle [![Codacy Badge](https://app.codacy.com/project/badge/Grade/980ea2efc85a427ea909518f29506ff6)](https://app.codacy.com/gh/CommonGateway/CustomerNotificationsBundle/dashboard?utm_source=gh\&utm_medium=referral\&utm_content=\&utm_campaign=Badge_grade)

A bundle containing logic for handling notifications & sending email/sms messages.

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

...

email Action config example \
For more examples see Actions in the /Installation/Action folder that use the ActionHandler `EmailHandler`.

sms Action config example \
For more examples see Actions in the /Installation/Action folder that use the ActionHandler `SmsHandler`.

## Configuration for notifications

It is also possible to trigger the email and/or SMS Actions you configured through notifications. The CustomerNotificiationsBundle adds a new Common Gateway endpoint that can be used to send your [ZGW notifications](https://vng-realisatie.github.io/gemma-zaken/themas/achtergronddocumentatie/notificaties) to: \
`{{gateway-domain}}/api/notifications`

All notifications send to this endpoint will trigger a [Common Gateway event](https://commongateway.github.io/CoreBundle/pages/Features/Events): \
`notifications.notification.created`

And by creating a Common Gateway Action using the `NotificationsHandler` [ActionHandler](https://commongateway.github.io/CoreBundle/pages/Features/Action_handlers) you can configure which notifications should trigger a new [Common Gateway event](https://commongateway.github.io/CoreBundle/pages/Features/Events) for sending an email or sms.

### How to create a notification Action

Normally you can create Actions through the Gateway admin UI, but the notification Action has some complex configuration that can currently not be configured with the Gateway UI.
Because of this it is recommended to include your Action directly in the installation files of the bundle ([Common Gateway plugin](https://commongateway.github.io/CoreBundle/pages/Features/Plugins)) you are working with or use an API-platform tool like postman to directly POST (, PATCH or UPDATE) your Action on the Common Gateway you are working with.

So now that you now how to create a notification Action, you should also know and understand the requirements of the configuration for a (notification) Action:

* A `name`, your Action is going to need a name.
* A `reference`, each Action needs a unique reference url starting with `https://{your-domain}/action/` and ending with `.action.json`, something like: `"https://commongateway.nl/action/notifications.ZaakCreatedAction.action.json"`
* Each Action needs to listen to one or more [Common Gateway events](https://commongateway.github.io/CoreBundle/pages/Features/Events) you can add this to the `listens` array of your Action. This will most likely be `["notifications.notification.created"]` if you are working with [ZGW notifications](https://vng-realisatie.github.io/gemma-zaken/themas/achtergronddocumentatie/notificaties).
* Some `conditions`, these conditions determine when your notification Action should be triggered and throw the event that will trigger the Action sending an email or sms. [Below](#notification-action-conditions) this summary/list you will find an example.
* The `class`, this should be `"CommonGateway\\CustomerNotificationsBundle\\ActionHandler\\NotificationsHandler"` for your notification Action.
* A `configuration` array containing specific configuration for getting and passing information to your email and/or sms Actions, probably the most complex thing in this list, so because of that [below](#notification-action-conditions) this summary/list you will find a more detailed explanation and example.

### Notification Action conditions

In order to only send an email or sms for a specific type of notification you can use the Action `conditions` in combination with the Action configuration to only make your Action trigger for the notifications you want.
Action conditions use [JsonLogic](https://jsonlogic.com/) to compare the Action data with your conditions.

Here is an example of the conditions for a 'case created' / 'zaak aangemaakt' notification Action:
```json
{
    "and": [
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
> For more examples see all Actions in the root/Installation/Action folder that use the ActionHandler (class) `CommonGateway\CustomerNotificationsBundle\ActionHandler\NotificationsHandler`.

In some cases you want to check a little bit more than is possible with only the Action conditions. 
Such as getting and checking information from the ZGW notification hoofdObject or resourceUrl objects. 
To learn more about this please check the Action configuration `extraConditions` [below](#extraconditions).

### Notification Action configuration

The configuration of your notification Action can be used for a few things, we will go into detail here what you can configure.
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
> For more examples see all Actions in the root/Installation/Action folder that use the ActionHandler (class) `CommonGateway\CustomerNotificationsBundle\ActionHandler\NotificationsHandler`.

#### extraConditions

The extra conditions for this action, this makes it possible to check properties from an object in a Source outside the Gateway and use that data as extra conditions for running this action. 
All conditions in the `"conditions"` array are checked. 

Only properties/keys defined in a `sourceProperties` array can be used to check conditions for in the `"conditions"` array. 
See the example [above](#notification-action-configuration), we check if `isEindstatus = true`, `isEindstatus` is present in a `sourceProperties` array.

With `getObjectDataConfig` you can configure how a source will be called, to get the `sourceProperties` you need for your `"conditions"`. 
`getObjectDataConfig` can be used recursively, if you do this you will need to add the array property `forParentProperties` containing the `sourceProperties` you would like to use to call another Source with. 
See the example [above](#notification-action-configuration), `"statustype"` is a property on the source containing an url, it is present in the first `sourceProperties` array and in the `forParentProperties` after that.

`getObjectDataConfig` must always have the properties: 
- `source` reference of the source to call.
- `sourceProperties` properties to use from source response. 
- & one of: 
  - `notificationProperty` get url from the notification to call on a source.
  - `sourceEndpoint` define a specific endpoint to call on a source.
  - `forParentProperties` in case of recursion add the sourceProperty name here, that has an url in the value, so that can be used to call another (or the same) source.

But `getObjectDataConfig` can also have the property: 
- `sourceQuery` query to use to call the source.

#### hoofdObjectSource

When this property is set the data from the notification hoofdObject will be available to use in your email & sms template. 
The given source (reference) will be called using the notification hoofdObject url and the return value will be passed through the thrown email/sms event. \
Only set this if you need it.

#### resourceUrlSource

Does exactly the same as hoofdObjectSource but for the notification resourceUrl instead. (not present in the example [above](#notification-action-configuration))\
Only set this if you need it.

#### emailConfig

This contains the configuration for sending an email after the notification has been received. 
If not present it will not be possible for emails to be sent. 

- `getObjectDataConfig` can be used to configure how to find and add the data of one Common Gateway Object to the email Action data (and email message through the email template).
- `objectConditions` TODO
- `throw` is the event we should throw to trigger another [EmailHandler action](#configuration-for-emails-andor-sms) that will send the actual email. 

Basic details about how `getObjectDataConfig` works can be found in the description of the [extraConditions](#extraconditions) property, please take a look at that first. 
Good to know & emailConfig specific properties: 
- `source` reference of the source to call. 
- `sourceProperties` this is the array with property names to get from the response of the source. 
- `searchSchemas` array with Schema references to use when searching an Object in de Gateway. 
- `searchQuery` query array to use when searching an Object in de Gateway, use {{sourcePropertyName}} here to insert the values got using `sourceProperties`. See example [above](#notification-action-configuration).

> **Note:** 
> that it also possible to use `getObjectDataConfig` recursively, see [extraConditions](#extraconditions) for how this is done.

#### smsConfig

This contains the configuration for sending an SMS after the notification has been received.
If not present it will not be possible for sms to be sent. 

- `getObjectDataConfig` can be used to configure how to find and add the data of one Common Gateway Object to the SMS Action data (and SMS message through the SMS template), if set to `"sameAsEmail"` the same object (response from sources) as for email will be used (or the same configuration).
- `objectConditions` TODO
- `throw` is the event we should throw to trigger another [SMSHandler action](#configuration-for-emails-andor-sms) that will send the actual sms. 

For more details about how `getObjectDataConfig` works, please see the [emailConfig property](#emailconfig).

> **Note:**
> smsConfig works exactly the same as the emailConfig except for the use of`"sameAsEmail"`.

#### createObjectConfig

This currently doesn't do anything, this is a work in progress. \
When this is finished it can however be used to create specific Common Gateway Objects at the end of handling a notification. To create for example a 'klantcontact' Object after the email and/or SMS has been sent. 