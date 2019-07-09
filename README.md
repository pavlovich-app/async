CONFIGURATION RABBITMQ AND MULTI CURL COMPONENTS IN YII2 APPLICATION
=====================================================================
```
'components' => [
    'rabbit' => require __DIR__ . '/rabbit.php',
    'client' => require __DIR__ . '/client.php',
],
```

RabbitMQ file rabbit.php
=========================
````
<?php

return [
    'class' => pavlovich\async\components\RabbitComponent::class,

    'credentials' => [
        'host' => 'localhost',
        'port' => '5672',
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'connection_timeout' => 500,
        'read_write_timeout' => 500,
        'keepalive' => false,
        'heartbeat' => 0,
    ],

    'attempts' => [
        'number' =>  6,
        'handler' => \common\components\ErrorComponent::class,
    ],

    'exchanges' => [
        [
            'name' => 'h_exchanger',
            'type' => 'direct',
        ],

        [
            'name' => 'c_exchanger',
            'type' => 'direct',
        ],

        [
            'name' => 'm_exchanger',
            'type' => 'direct',
        ],
    ],
    'queues' => [
        [
            'name' => 'h_queue',
            'exclusive' => false,
        ],
        [
            'name' => 'c_queue',
            'exclusive' => false,
        ],
        [
            'name' => 'm_queue',
            'exclusive' => false,
        ],
    ],
    'bindings' => [
        [
            'queue'    => 'h_queue',
            'exchange' => 'h_exchanger',
        ],
        [
            'queue'    => 'c_queue',
            'exchange' => 'c_exchanger',
        ],
        [
            'queue'    => 'm_queue',
            'exchange' => 'm_exchanger',
        ],
    ],
    'producers' => [
        [
            'name' => 'h_producer',
            'safe' => true,
            'content_type' => 'text/plain',
            'delivery_mode' => 2,
            'serializer' => 'serialize',
        ],
        [
            'name' => 'c_producer',
            'safe' => true,
            'content_type' => 'text/plain',
            'delivery_mode' => 2,
            'serializer' => 'serialize',
        ],
        [
            'name' => 'm_producer',
            'safe' => true,
            'content_type' => 'text/plain',
            'delivery_mode' => 2,
            'serializer' => 'serialize',
        ],
    ],
];
````

RabbitMQ file client.php
=========================
````
<?php return [ 'class' => pavlovich\async\components\ClientComponent::class ];
````