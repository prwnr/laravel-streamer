# Laravel Streamer

Streamer is a Laravel package for events functionality between different applications, powered by Redis Streams.
This package utilizes all main commands of Redis 5.0 Streams providing a simple usage of Streams as Events.

Main concept of this package is to provide easy way of emitting new events from your application and to allow listening to them in your other applications that are using same Redis server.

# Installation
1. Install package via composer command `composer require prwnr/laravel-streamer` or by adding it to your composer.json file with version.
2. Discover the package
3. Publish configuration with `vendor:publish` command.
4. Make sure that you have running Redis 5.0 instance and that Laravel is configured to use it 
5. Make sure that you have [PHPRedis extension](https://github.com/phpredis/phpredis) installed.
# Usage
There are two main ends of this package usage - emiting new event and listening to events. Whereas emiting requires a bit more work to get it used, such as creating own Event classes, then listening to events is available with artisan command and is working without much work needed.

## Version Compatibility

 Laravel  | Streamer    | Redis driver|
:---------|:------------|:------------|
 5.6.x    | 1.6.x       | Predis      |
 5.7.x    | 1.6.x       | Predis      |
 5.8.x    | 1.6.x       | Predis      |
 6.x      | 2.x         | PhpRedis    |
 7.x      | ^2.1        | PhpRedis    |

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

### Replaying Events

Since 2.2 version, Stream events can be "replayed". This means, that the specific message (with a unique identifier) 
can be "reconstructed" until "now" (or until a selected date). 

What "replaying" messages really means? It means, that all the messages that are in the stream, will be read from the
very beginning, and payload of each single entry will be "combined" into a final version of the message - 
each filed will be replaced with its "newer" value, if such exists in the history. 

This is going to be useful with events that don't hold all the information about the resource they may represent, 
but have only data about fields that changed. 

So, for example having a resource with fields `name` and `surname`, 
we will emit 3 different events:
- first for its creation populating both fields with values (`name: foo; surname: bar`)
- second event that will change only `name` into `foo bar`
- third event that changes name again to `bar foo`. 

While replaying this set of messages (remember that each one has the same unique identifier) 
our final replayed resource will be: `name: bar foo; surname: bar`. 
If we would replay the event until the time before third change, we would have `name: foo bar; surname: bar`
 
#### Usage

To make Event replayable, it needs to implement the `Prwnr\Streamer\Contracts\Replayable` contract. 
This will enforce adding a `getIdentifier` method, that should return unique identifier for the resource
(like UUID of the resource that this event represents). With this contract being fulfilled, all events that will go
through `Streamer` emit method, will be also "marked" as available to be replayed.

To actually replay messages, `Hsitory` interface implementation needs to be used. 

Method that should be used is: `replay(string $event, string $identifier, Carbon $until = null): array`.
This method will return the "current state" of the event, rebuilding it from its history. As seen in method definition,
it asks for event string name and resource identifier (that was applied by `Replayable` contract). Third parameter is
optional and if used, it will stop replaying messages when first message with matching date will be encountered.

### Eloquent Model Events

With use of a `EmitsStreamerEvents` trait you can easily make your Eloquent Models emit basic events.
This trait will integrate your model with Streamer and will emit events on actions like: `save`, `create` and `delete`.
It will emit an event of your model name with suffix of the action and a payload of what happened. In case of a `create`
and `save` actions the payload will have a list of changed fields and a before/after for each of those fields (with create action
fields before will basically have all values set to null), in case of a `delete` action, payload will simply state that the model has been deleted.
Each payload includes a `[key_name => key_value]` pair of your model ID. 

By default events will take names from their models with a suffix of the action, but the name can be changed by 
assigning it to a `baseEventName` attribute. This name will replace the model name but will keep suffix of what action has been taken.

Check examples directory in this package to see how can you exactly use each command with package Stream and Consumer instances.

### Helpful Commands

#### List

`streamer:list` command will list all registered events, and their associated listeners. 
The option `--compact` will yield only a list of the events, skipping listeners column. 

This command may be useful to see what events are being actually handled by a listener, what can help to find out
what's missing. This list can be also used to start listening to available events by 3rd part app.