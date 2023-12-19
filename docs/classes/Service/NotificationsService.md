# CommonGateway\CustomerNotificationsBundle\Service\NotificationsService  

This service handles the incoming notifications. Creating email and/or SMS messages if configured to do so.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#notificationsservice__construct)||
|[notificationsHandler](#notificationsservicenotificationshandler)|Handles incoming notification api-call and is responsible for generating a response.|




### NotificationsService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### NotificationsService::notificationsHandler  

**Description**

```php
public notificationsHandler (array $data, array $configuration)
```

Handles incoming notification api-call and is responsible for generating a response. 

Might also send an email and/or SMS after, etc. 

**Parameters**

* `(array) $data`
: The data array  
* `(array) $configuration`
: The configuration array  

**Return Values**

`array`

> A handler must ALWAYS return an array


<hr />

