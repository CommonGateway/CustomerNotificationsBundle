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

email action config example \
For more examples see actions in the /Installation/Action folder that use the ActionHandler `EmailHandler`.

sms action config example \
For more examples see actions in the /Installation/Action folder that use the ActionHandler `SmsHandler`.

## Configuration for notifications

It is also possible to trigger the email and/or SMS actions you configured through notifications. The CustomerNotificiationsBundle adds a new Common Gateway endpoint that can be used to send your [ZGW notifications](https://vng-realisatie.github.io/gemma-zaken/themas/achtergronddocumentatie/notificaties) to: \
`{{gateway-domain}}/api/notifications`

All notifications send to this endpoint will trigger a [Common Gateway event](https://commongateway.github.io/CoreBundle/pages/Features/Events): \
`notifications.notification.created`

And by creating a Common Gateway Action using the `NotificationsHandler` [ActionHandler](https://commongateway.github.io/CoreBundle/pages/Features/Action_handlers) you can configure which notifications should trigger a new [Common Gateway event](https://commongateway.github.io/CoreBundle/pages/Features/Events) for sending an email or sms.

### How to create a notification Action

Normally you can create Actions through the Gateway admin UI, but the notification Action has some complex configuration that can currently not be configured with the Gateway UI.
Because of this it is recommended to include your Action directly in the installation files of the bundle ([Common Gateway plugin](https://commongateway.github.io/CoreBundle/pages/Features/Plugins)) you are working with or use an API-platform tool like postman to directly POST (, PATCH or UPDATE) your Action in the Common Gateway you are working with.

So now that you now how to create a notification Action, you should also know and understand the requirements of the configuration for a (notification) Action:
- A `name`, your action is going to need a name.
- A `reference`, each action needs a unique reference url starting with `https://{your-domain}/action/` and ending with `.action.json`, something like: `"https://commongateway.nl/action/notifications.ZaakCreatedAction.action.json"`
- Each Action needs to listen to one or more [Common Gateway events](https://commongateway.github.io/CoreBundle/pages/Features/Events) you can add this to the `listens` array of your Action. This will most likely be `["notifications.notification.created"]` if you are working with [ZGW notifications](https://vng-realisatie.github.io/gemma-zaken/themas/achtergronddocumentatie/notificaties).
- Some `conditions`, these conditions determine when your notification action should be triggered and throw the event that will trigger the action sending an email or sms. Below this summary/list you will find an example.
- The `class`, this should be `"CommonGateway\\CustomerNotificationsBundle\\ActionHandler\\NotificationsHandler"` for your notification Action.
- A `configuration` array containing specific configuration for getting and passing information to your email and/or sms actions, probably the most complex thing in this list, so because of that below this summary/list you will find a more details explanation and example. 

...
notification action conditions example

notification action config example \
For more examples see actions in the /Installation/Action folder that use the ActionHandler `NotificationsHandler`.
