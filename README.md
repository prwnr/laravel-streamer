# Laravel Streamer

Streamer is a Laravel package for events functionality between different applications, powered by Redis Streams.
This package utilizes all main commands of Redis 5.0 Streams providing a simple usage of Streams as Events.

Main concept of this package is to provide easy way of emitting new events from your application and to allow listening to them in your other applcations that are using same Redis server.

# Installation
1. Install package via composer command `composer require prwnr/laravel-streamer` or by adding it to your composer.json file with version.
2. Discover the package
3. Publish configuration with `vendor:publish` command.
4. Make sure that you have running Redis 5.0 instance and that Laravel is configured to use it 
5. Change redis profile in `database.php` by adding this to redis configuration:
    ```php
    'options' => [
        'profile' => '5.0'
    ],
    ```
    or otherwise Laravel will use default 3.2 Redis profile and none of the Redis Stream commands will work

# Usage
There are two main ends of this package usage - emiting new event and listening to events. Whereas emiting requires a bit more work to get it used, such as creating own Event classes, then listening to events is available with artisan command and is working without much work needed.

### Emiting new events

In order to emit new event few things needs to be done. 
First of all, you will need to have a valid class that implements `Prwnr\Streamer\Contracts\Event` like this:
```php
class ExampleStreamerEvent implements Prwnr\Streamer\Contracts\Event {
    /**
     * Require name method, must return a string.
     * Event name can be anything, but remember that it will be used for listening
     */
    public function name(): string 
    {
        return 'example.streamer.event';
    }
    /**
     * Required type method, must return a string.
     * Type can be any string or one of predefined types from Event
     */
    public function type(): string
    {
        return Event::TYPE_EVENT;
    }
    /**
     * Required payload method, must return array
     * This array will be your message data content
     */
    public function payload(): array
    {
        return ['message' => 'content'];
    }
}
```
Then, at any point in your application all you need to do is to emit that event by using either `Streamer` instance or `Streamer` facade.
```php
$event = new ExampleStreamerEvent();
$id = \Prwnr\Streamer\Facades\Streamer::emit($event);
```
This will create a message on a stream named (if such does not exists): `example.streamer.event`. Emit method will return an ID if emitting your event ended up with success. 

### Listening for new messages on events

In order to listen to event you will have to properly configure `config/streamer.php` (or use `ListenerStack::add` method) and run `php artisan streamer:listen` command. 
At config file you will find *Application listeners* configuration with default values for it, that should be changed if you want to start listening with streamer listen command.
Other way to add listeners for events is to use `ListenersStack` static class. This class is being booted with listeners from configuration file and is then used by 
command to get all of them. So, the addition of this class is that it allows adding listeners not only via configuration file, but also programmatically. 

Remember that local listener should implement `MessageReceiver` contract to ensure that it has `handle` method
which accepts `ReceivedMessage` as an argument.
```php
/*
|--------------------------------------------------------------------------
| Application listeners
|--------------------------------------------------------------------------
|
| Listeners classes that should be invoked with Streamer listen command
| based on streamer.event.name => [local_handlers] pairs
|
| Local listeners should implement MessageReceiver contract
|
*/
'listen_and_fire' => [
    'example.streamer.event' => [
        //List of local listeners that should be invoked
        //\App\Listeners\ExampleListener::class
    ],
],
```
Above configuration is an array of Streamer events with array of local listeners related to Streamer event. When listening to `example.streamer.event` all local listeners from its config definition 
will be created and their `handle` method fired with message received from Stream. 
For listener instance creation this package uses Laravel Container, therefore you can type hint anything into your 
listener constructor to use Laravel dependency injection. 

To start listening for event, use this command:
```bash
php artisan streamer:listen example.streamer.event 
```
That's a basic usage of listening command, where event name is a required argument. In this case it simply starts listening for only new events.
This command however has few options that are extending its usage, those are:
```text
--group= : Name of your streaming group. Only when group is provided listener will listen on group as consumer
--consumer= : Name of your group consumer. If not provided a name will be created as groupname-timestamp
--reclaim= : Miliseconds of pending messages idle time, that should be reclaimed for current consumer in this group. Can be only used with group listening
--last_id= : ID from which listener should start reading messages
```

### Channels

Channels are similar to listener, but are dedicated to a single-name events with consumer
and group associated to them by default. They are made to be a blocking message receivers.

You can achieve channel functionality by extending `Channel` class (and implementing its abstract methods).

Channels are simple to use, they have two functional methods.
1) `send` - sends message to stream (with name that is based on class name),
2) `receive` - awaits for stream messages and calls callback function that is to be determined by the user.

When receiving messages from channel, there are few things to know. Closure should always
return either `true` or `false`. `true` will keep the listening alive, while `false` will
break out of the listening loop (note: only after first processing all already received messages). The last thing to know is that, when streamer will time out, 
it will inject empty `ReceivedMessage` instance into closure call. You can determine empty message
by checking if it has ID or not (by calling `$message->getId()`).

Example usage:
```php
$channel = new FooBarChannel(); //channel instance (extends Channel)
$channel->send(['foo' => 'bar']); //sending message to channel

//receiving message from channel
$channel->receive(function (ReceivedMessage $message) {
    $this->assertEquals(['foo' => 'bar'], $message->get());

    return false;
});
``` 

### Eloquent Model Events

With use of a `EmitsStreamerEvents` trait you can easily make your Eloquent Models emit basic events.
This trait will integrate your model with Streamer and will emit events on actions like: `save`, `create` and `delete`.
It will emit an event of your model name with suffix of the action and a payload of what happened. In case of a `create`
and `save` actions the payload will have a list of changed fields and a before/after for each of those fields (with create action
fields before will basically have all values set to null), in case of a `delete` action, payload will simply state that the model has been deleted.
Each payload includes a `[key_name => key_value]` pair of your model ID. 

By default events will take names from their models with a suffix of the action, but the name can be changed by 
assigning it to a `baseEventName` attribute. This name will replace the model name but will keep suffix of what action has been taken.

# What's more
Despite of giving an option to emit and listen to events, this package is also giving a nice abstraction of Redis Streaming commands. Commands that are implemented:
* XACK
* XADD
* XCLAIM
* XDEL
* XGROUP
* XINFO
* XLEN
* XPENDING
* XRANGE
* XREAD
* XREADGROUP
* XREVRANGE

Check examples directory in this package to see how can you exactly use each command with package Stream and Consumer instances.
