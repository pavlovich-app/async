<?php
/**
 * User: aleksandr.pavlovich
 * Date: 02.11.18
 */
namespace async\components;

use yii\base\Component;

class RabbitComponent extends Component
{
    public $connect = null;
    public $channel = null;
    public $auto_declare = null;
    public $credentials  = [];
    public $producers    = [];
    public $consumers    = [];
    public $queues       = [];
    public $exchanges    = [];
    public $bindings     = [];

    private $q  = null;
    private $ex = null;

    const DEF_CREDENTIALS = [
        'host'      => 'localhost',
        'port'      => 5672,
        'user'      => 'guest',
        'password'  => 'guest',
        'vhost'     => '/',
        'insist'    => false,
        'login_method'   => 'AMQPLAIN',
        'login_response' => null,
        'locale'         => 'en_US',
        'connection_timeout' => 3.0,
        'read_write_timeout' => 3.0,
        'context'   => null,
        'keepalive' => false,
        'heartbeat' => 0,
    ];

    public $attempts = [
        'number' =>  1,
        'handler' => null,
    ];

    /**
     * RabbitComponent constructor.
     * @param array $config
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function __construct( $config = [] )
    {
        parent::__construct($config);
        $this->credentials = array_merge(static::DEF_CREDENTIALS, $this->credentials);

        $this->connect();

        $this->declare($this->queues, $this->exchanges);
        $this->bind( $this->bindings );
    }

    /**
     * close channel and connection
     */
    public function __destruct()
    {
        $this->channel->close();
    }

    /**
     * method try connect to rabbitMQ
     */
    private function connect(): void {
        $this->connect = new \AMQPConnection($this->credentials);
        $this->connect->setHost($this->credentials['host']);
        $this->connect->setPort($this->credentials['port']);
        $this->connect->setLogin($this->credentials['user']);
        $this->connect->setPassword($this->credentials['password']);

        $this->connect->connect();
        $this->channel = new \AMQPChannel($this->connect);
        $this->channel->qos(null, 1);
    }

    /**
     * @param array $queues
     * @param array $exchanges
     * @return RabbitComponent
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function declare( array $queues = [], array $exchanges = [] ): RabbitComponent
    {
        foreach( $exchanges as $exchange ){
            $ex = new \AMQPExchange($this->channel);
            $ex->setName($exchange['name']);
            $ex->setType(AMQP_EX_TYPE_DIRECT);
            $ex->setFlags(AMQP_DURABLE | AMQP_IFUNUSED);
            $ex->declare();
            $this->ex[$exchange['name']] = $ex;
        }

        foreach( $queues as $queue ){
            $q = new \AMQPQueue($this->channel);
            $q->setName($queue['name']);
            $q->setFlags(AMQP_IFUNUSED | AMQP_DURABLE );
            $q->declare();
            $this->q[$queue['name']] = $q;
        }

        return $this;
    }

    /**
     * @param string ...$queue
     * @return RabbitComponent
     */
    public function purge( string ... $queue ): RabbitComponent
    {
        foreach($queue as $item){
            if( isset($this->q[$item]) ){
                $this->q[$item]->purge();
            }
        }
        return $this;
    }

    /**
     * @param array $bindings
     * @return RabbitComponent
     */
    public function bind( array $bindings = [] ): RabbitComponent
    {
        foreach( $bindings as $binding ){
            $this->q[$binding['queue']]->bind($binding['exchange']);
        }
        return $this;
    }

    /**
     * @param array $message
     * @param string $producer
     * @param string|null $routing_key
     * @return bool
     */
    public function sendMessage( array $message = [], string $producer = '', ?string $routing_key = null ): bool
    {
        if(!isset($message['_attempts'])){ $message['_attempts'] = (int) 0; }
        ++$message['_attempts'];

        if( $message['_attempts'] > $this->attempts['number'] ){
            if( is_callable($this->attempts['handler']) ){
                return $this->attempts['handler'] ( $message );
            }
            if(is_string($this->attempts['handler'])){
                return (new $this->attempts['handler'])->execute( $message );
            }

            return false;
        }

        return $this->ex[$producer]->publish( json_encode($message), $routing_key, AMQP_MANDATORY, [ 'delivery_mode' => 2, ] );
    }

    /**
     * @param string $queue
     * @param callable $callback
     * @param array $options
     */
    public function consume( string $queue, callable $callback, array $options = [] ): void
    {
        $this->q[$queue]->consume( $callback );
    }

}