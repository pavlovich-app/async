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


Using RabbitMQ queues. Send message
==================================
You can send message from anywhere in your application:
````
\Yii::$app->rabbit->sendMessage([ 'project_id' => 1, 'count' => 10] 'm_exchanger');
````


Consumer example
====================
````
<?php
namespace console\consumers;

use common\helpers\StringHelper;
use backend\workers\PWorker;
use common\components\Logger;

class CloudConsumer
{
    /**
     * @param \AMQPEnvelope $msg
     * @param \AMQPQueue $queue
     */
    public function execute( \AMQPEnvelope $msg, \AMQPQueue $queue )
    {
        $message = json_decode( $msg->getBody(), 1 );

        \Yii::$app->client->get()->send([$message], function($response, $index) use ($message) {

            $response = json_decode($response->getBody()->getContents(), 1);
            if( $response ){
                try{
                    $transaction = \Yii::$app->db->beginTransaction();
                    ...
                    \Yii::$app->rabbit->sendMessage([ 'p_id' => 1, 'from' => 'Oleksandr', 'to' => 'Vitaliy', 'm_exchanger');
                    ...
                    $transaction->commit();

                }catch(\Exception $exception ){
                    $transaction->rollBack();
                    ...
                    \Yii::$app->rabbit->sendMessage($message, 'cloud_exchanger');
                    ...
                    Logger::write('error', $exception->getMessage(), ['trace'=> $exception->getTraceAsString()]);
                }
            }

        }, function($exception, $index){
            Logger::write('error', $exception->getMessage(), ['trace'=> $exception->getTraceAsString()]);
        }, 1);
    }


````

Running Consumer example
========================
````
try {
    \Yii::$app->rabbit->consume('hub_queue', function ($msg, $queue) {
        
        (new HubConsumer)->execute($msg, $queue);
        
        $queue->ack($msg->getDeliveryTag());
        
    });
        
    } catch ( \Exception $exception ) { Logger::write('error', $exception->getMessage(), ['trace'=> $exception->getTraceAsString()]); }
}
````

Asynchronous request sending. Asynchronous response
==============================
````
foreach($values as $val){
    $requests[] = ['url' => $val['url'], 'params' => $val['params'] ];
}


\Yii::$app->client->get()->send($requests, function($response, $index) use ($message) {
        $response = json_decode($response->getBody()->getContents(), 1);
    }, function($exception, $index){
    Logger::write('error', $exception->getMessage(), ['trace'=> $exception->getTraceAsString()]);
}, 60)  // 60 - Number of streams
````


