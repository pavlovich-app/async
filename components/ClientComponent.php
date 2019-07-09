<?php
/**
 * Created by PhpStorm.
 * User: oleksandr
 * Date: 13.02.19
 * Time: 12:55
 */

namespace async\components;

use yii\base\Component;
use GuzzleHttp\{Client, Pool};

class ClientComponent extends Component
{
    public $method = 'POST';

    /**
     * @param array $q
     * @param callable $callback
     * @param callable $error
     * @param int $connections
     * @return $this
     */
    public function send( array $q, callable $callback, callable $error, int $connections = 5 )
    {
        $client = new Client();
        $requests = function ($q) use ($client) {
            foreach ( $q as $request ) {
                yield function() use ($client, $request) {
                    $defaultHeaders = ['headers' => array_merge([ 'User-Agent' => 'MasterPanel bot'], $request['headers'] ?? [], ['connect_timeout' => 10])];
                    if( $this->method == 'POST' ){
                        $defaultHeaders['form_params'] = $request['params'];
                        return $client->postAsync($request['url'], $defaultHeaders);
                    }else{
                        $request['url'] = $request['url'].'?'.http_build_query($request['params']);
                        return $client->getAsync($request['url'], $defaultHeaders);
                    }
                };
            }
        };

        $pool = new Pool( $client, $requests($q), [
            'concurrency' => $connections,
            'fulfilled'   => function ($response, $index) use ($callback) { $callback($response, $index); },
            'rejected'    => function ($reason, $index) use ( $error ){ $error($reason, $index); },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $this;
    }

    /**
     * @return $this
     */
    public function get()
    {
        $this->method = 'GET';
        return $this;
    }

    /**
     * @return $this
     */
    public function post()
    {
        $this->method = 'POST';
        return $this;
    }
}