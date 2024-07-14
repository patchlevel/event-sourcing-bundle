# Configuration

!!! info

    You can find out more about event sourcing in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/). 
    This documentation is limited to bundle integration and configuration.
    
!!! tip

    We provide a [default configuration](./installation.md#configuration-file) that should work for most projects.
    
## Aggregate

A path must be specified for Event Sourcing to know where to look for your aggregates.
If you want you can use glob patterns to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  aggregates: '%kernel.project_dir%/src/*/Domain'
```
Or use an array to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  aggregates:
    - '%kernel.project_dir%/src/Hotel/Domain'
    - '%kernel.project_dir%/src/Room/Domain'
```
!!! note

    The library will automatically register all classes marked with the `#[Aggregate]` attribute in the specified paths.
    
!!! tip

    If you want to learn more about aggregates, read the [library documentation](https://patchlevel.github.io/event-sourcing-docs/latest/aggregate/).
    
## Events

A path must be specified for Event Sourcing to know where to look for your events.
If you want you can use glob patterns to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  events: '%kernel.project_dir%/src/*/Domain/Event'
```
Or use an array to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  events:
    - '%kernel.project_dir%/src/Hotel/Domain/Event'
    - '%kernel.project_dir%/src/Room/Domain/Event'
```
!!! tip

    If you want to learn more about events, read the [library documentation](https://patchlevel.github.io/event-sourcing-docs/latest/events/).

## Custom Headers

If you want to implement custom headers for your application, you must specify the
paths to look for those headers.
If you want you can use glob patterns to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  headers: '%kernel.project_dir%/src/*/Domain/Header'
```
Or use an array to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  headers:
    - '%kernel.project_dir%/src/Hotel/Domain/Header'
    - '%kernel.project_dir%/src/Room/Domain/Header'
```
!!! tip

    If you want to learn more about custom headers, read the [library documentation](https://event-sourcing.patchlevel.io/latest/message/#custom-headers).
    
## Connection

You have to specify the connection url to the event store.

```yaml
patchlevel_event_sourcing:
  connection:
    url: '%env(EVENTSTORE_URL)%'
```
!!! note

    You can find out more about how to create a connection 
    [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)
    
### Doctrine Bundle

If you have installed the [doctrine bundle](https://github.com/doctrine/DoctrineBundle),
you can also define the connection via doctrine and then use it in the store.

```yaml
doctrine:
    dbal:
        connections:
            eventstore:
                url: '%env(EVENTSTORE_URL)%'

patchlevel_event_sourcing:
    connection:
        service: doctrine.dbal.eventstore_connection
```
!!! warning

    If you want to use the same connection as doctrine orm, then you have to set the flag `merge_orm_schema`. 
    Otherwise you should avoid using the same connection as other tools.
    
!!! note

    You can find out more about the dbal configuration 
    [here](https://symfony.com/bundles/DoctrineBundle/current/configuration.html).
    
## Store

The store and schema is configurable.

### Change table Name

You can change the table name of the event store.

```yaml
patchlevel_event_sourcing:
    store:
        table_name: 'my_event_store'
```
### Merge ORM Schema

You can also merge the schema with doctrine orm. You have to set the following flag for this:

```yaml
patchlevel_event_sourcing:
    store:
        merge_orm_schema: true
```
!!! warning

    If you want to merge the schema, then the same doctrine connection must be used as with the doctrine orm. 
    Otherwise errors may occur!
    
!!! note

    All schema relevant commands are removed if you activate this option. You should use the doctrine commands then.
    
!!! tip

    If you want to learn more about store, read the [library documentation](https://patchlevel.github.io/event-sourcing-docs/latest/store/).
    
## Migration

You can use [doctrine migrations](https://www.doctrine-project.org/projects/migrations.html) to manage the schema.

```yaml
patchlevel_event_sourcing:
    migration:
        namespace: EventSourcingMigrations
        path: "%kernel.project_dir%/migrations"
```
## Subscription

!!! tip

    You can find out more about subscriptions in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/subscription/).
    
### Catch Up

If aggregates are used in the processors and new events are generated there,
then they are not part of the current subscription engine `run` and will only be processed during the next run or boot.
This is usually not a problem in dev or prod environment because a worker is used
and these events will be processed at some point. But in testing it is not so easy.
For this reason, you can activate the `catch_up` option.

```yaml
patchlevel_event_sourcing:
    subscription:
        catch_up: true
```
### Throw on Error

You can activate the `throw_on_error` option to throw an exception if a subscription engine run has an error.
This is useful for testing or development to get directly feedback if something is wrong.

```yaml
patchlevel_event_sourcing:
    subscription:
        throw_on_error: true
```
!!! warning

    This option should not be used in production. The normal behavior is to log the error and continue.
    
### Run After Aggregate Save

If you want to run the subscription engine after an aggregate is saved, you can activate this option.
This is useful for testing or development, so you don't have run a worker to process the events.

```yaml
patchlevel_event_sourcing:
    subscription:
        run_after_aggregate_save: true
```
### Auto Setup

If you want to automatically setup the subscription engine, you can activate this option.
This is useful for development, so you don't have to setup the subscription engine manually.

```yaml
patchlevel_event_sourcing:
    subscription:
        auto_setup: true
```
!!! note

    This works only before each http requests and not if you use the console commands.
    
### Rebuild After File Change

If you want to rebuild the subscription engine after a file change, you can activate this option.
This is also useful for development, so you don't have to rebuild the projections manually.

```yaml
patchlevel_event_sourcing:
    subscription:
        rebuild_after_file_change: true
```
!!! note

    This works only before each http requests and not if you use the console commands.
    
## Event Bus

You can enable the event bus to listen for events and messages synchronously.
But you should consider using the subscription engine for this.

```yaml
patchlevel_event_sourcing:
    event_bus: ~
```
!!! note

    Default is the patchlevel [event bus](https://patchlevel.github.io/event-sourcing-docs/latest/event_bus/).
    
### Patchlevel (Default) Event Bus

First of all we have our own default event bus.
This works best with the library, as the `#[Subscribe]` attribute is used there, among other things.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: default
```
!!! note

    You don't have to specify this as it is the default value.
    
### Symfony Event Bus

But you can also use [Symfony Messenger](https://symfony.com/doc/current/messenger.html).
To do this, you first have to define a suitable message bus.
This must be "allow_no_handlers" so that this messenger can be an event bus according to the definition.

```yaml
# messenger.yaml
framework:
    messenger:
        buses:
            event.bus:
                default_middleware: allow_no_handlers
```
We can then use this messenger or event bus in event sourcing:

```yaml
patchlevel_event_sourcing:
    event_bus:
        service: event.bus
```
Since the event bus was replaced, event sourcing own attributes no longer work.
You use the Symfony attributes instead.

```php
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler('event.bus')]
class SmsNotificationHandler
{
    public function __invoke(Message $message): void
    {
        if (!$message instanceof GuestIsCheckedIn) {
            return;
        }

        // ... do some work - like sending an SMS message!
    }
}
```
#### Command Bus

If you use a command bus or cqrs as a pattern, then you should define a new message bus.
The whole thing can look like this:

```yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus: ~
            event.bus:
                default_middleware: allow_no_handlers
```
!!! warning

    You should deactivate the autoconfigure feature for the handlers, 
    otherwise they will be registered in both messenger.
    
### PSR-14 Event Bus

You can also use any other event bus that implements the [PSR-14](https://www.php-fig.org/psr/psr-14/) standard.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: psr14
        service: my.event.bus.service
```
!!! note

    Like the Symfony event bus, the event sourcing attributes no longer work here.
    You have to use the system that comes with the respective psr14 implementation.
    
### Custom Event Bus

You can also use your own event bus that implements the `Patchlevel\EventSourcing\EventBus\EventBus` interface.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: custom
        service: my.event.bus.service
```
!!! note

    Like the Symfony event bus, the event sourcing attributes no longer work here.
    You have to use the system that comes with the respective custom implementation.
    
## Snapshot

You can use symfony cache to define the target of the snapshot store.

```yaml
framework:
    cache:
        default_redis_provider: 'redis://localhost'
        pools:
            event_sourcing.cache:
                adapter: cache.adapter.redis
```
After this, you need define the snapshot store.
Symfony cache implement the psr6 interface, so we need choose this type
and enter the id from the cache service.

```yaml
patchlevel_event_sourcing:
    snapshot_stores:
        default:
            service: event_sourcing.cache
```
Finally, you have to tell the aggregate that it should use this snapshot store.

```php
namespace App\Profile\Domain;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate(name: 'profile')]
#[Snapshot('default')]
final class Profile extends BasicAggregateRoot
{
    // ...
}
```
!!! note

    You can find out more about snapshots [here](https://patchlevel.github.io/event-sourcing-docs/latest/snapshots/).
    
## Cryptography

You can use the library to encrypt and decrypt personal data.
For this you need to enable the crypto shredding.

```yaml
patchlevel_event_sourcing:
    cryptography: ~
```
You can change the algorithm if you want.

```yaml
patchlevel_event_sourcing:
    cryptography:
        algorithm: 'aes-256-gcm'
```
!!! note

    You can find out more about personal data [here](https://patchlevel.github.io/event-sourcing-docs/latest/personal_data/).
    
## Clock

The clock is used to return the current time as DateTimeImmutable.

### Freeze Clock

You can freeze the clock for testing purposes:

```yaml
when@test:
    patchlevel_event_sourcing:
        clock:
            freeze: '2020-01-01 22:00:00'
```
!!! note

    If freeze is not set, then the system clock is used.
    
### Symfony Clock

Since symfony 6.2 there is a [clock](https://symfony.com/doc/current/components/clock.html) implementation
based on psr-20 that you can use.

```bash
composer require symfony/clock
```
```yaml
patchlevel_event_sourcing:
    clock:
        service: 'clock'
```
### PSR-20

You can also use your own implementation of your choice.
They only have to implement the interface of the [psr-20](https://www.php-fig.org/psr/psr-20/).
You can then specify this service here:

```yaml
patchlevel_event_sourcing:
    clock:
        service: 'my_own_clock_service'
```
