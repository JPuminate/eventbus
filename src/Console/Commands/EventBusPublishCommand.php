<?php

namespace JPuminate\Architecture\EventBus\Console\Commands;

use Illuminate\Console\Command;
use JPuminate\Architecture\EventBus\Connections\ConnectionConfiguration;
use JPuminate\Architecture\EventBus\Connections\ConnectionFactory;
use JPuminate\Architecture\EventBus\EventBusRabbitMQ;
use JPuminate\Architecture\EventBus\Exceptions\UnsupportedEvent;
use JPuminate\Architecture\EventBus\PingEvent;
use JPuminate\Contracts\EventBus\EventBus;
use JPuminate\Contracts\EventBus\Events\Event;
use RuntimeException;

/**
 * Created by PhpStorm.
 * User: Ouachhal
 * Date: 28/08/2017
 * Time: 20:18
 */

class EventBusPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $signature = 'eventbus:publish  {--async} {connection? : The name of connection} {--event=} {--args=} ';

    protected $description = 'push a simple ping event to test connectivity or a real event to notify subscribers';

    protected $eventBus;
    /**
     * @var ConnectionFactory
     */
    private $cnx_factory;


    public function __construct(ConnectionFactory $cnx_factory, EventBus $eventBus)
    {
        parent::__construct();
        $this->eventBus = $eventBus;
        $this->cnx_factory = $cnx_factory;
    }


    public function handle()
    {
        $this->setConnection();
        if ($this->option('event')) {
            if ($event = $this->supportedEvent()) {
                 if($args = $this->option('args')) {
                     $args = explode(",", $args);
                     $this->publishEvent(new $event(...$args));
                 }
                 else $this->publishEvent(new $event());
            } else throw new UnsupportedEvent();
        }
        else {
            $this->publishEvent(new PingEvent());
        }
    }


    private function setConnection(){
        $connection = $this->input->getArgument('connection');
       if( $connectionOption = $connection ? $this->laravel['config']['eventbus.connections.'.$connection]
           : $this->laravel['config']['eventbus.connections.'.$this->laravel['config']['eventbus.default']]) {
           $configuration = new ConnectionConfiguration($connectionOption['host'], $connectionOption['port'], $connectionOption['username'], $connectionOption['password']);
           $this->cnx_factory->setConnectionConfiguration($configuration);
       }
       else throw new RuntimeException("connection not found");
    }

    private function supportedEvent()
    {
        if (class_exists($class_name = $this->getLaravel()->getNamespace() .
            EventBusRabbitMQ::$NAME_SPACE . '\Events\\' .
            $this->option('event')))
            return $class_name;
        return false;
    }

    private function publishEvent(Event $event)
    {
       if($this->option("async")){
           $this->eventBus->publishAsync($event, false);
       }
       else $this->eventBus->publish($event, false);
    }
}