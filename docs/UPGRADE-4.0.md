---
searchable: false
---
# Upgrade 4.0

## Dependencies

The bundle now requires `patchlevel/event-sourcing` 4.0 and `patchlevel/hydrator` 2.0.
Both libraries bring their own breaking changes, for example renamed identifier classes,
removed stores or a new upcaster interface.
Follow the [event-sourcing upgrade guide](https://github.com/patchlevel/event-sourcing/blob/4.0.x/docs/UPGRADE-4.0.md)
and the [hydrator upgrade guide](https://github.com/patchlevel/hydrator/blob/2.0.x/UPGRADE-2.0.md) for these.
This guide only covers the changes of the bundle itself.

## Subscription

### Sync Subscriptions

The options `catch_up`, `throw_on_error` and `run_after_aggregate_save` have been removed
in favor of the new `sync` option.
Before, `catch_up` and `throw_on_error` decorated the global subscription engine,
so they also affected the worker and the console commands.
Now they only apply to the sync run after an aggregate has been saved.

before:

```yaml
when@dev:
    patchlevel_event_sourcing:
        subscription:
            catch_up: true
            throw_on_error: true
            run_after_aggregate_save: true
```
after:

```yaml
when@dev:
    patchlevel_event_sourcing:
        subscription:
            sync:
                throw_on_error: true
```
Catching up is now always active for sync runs.
The `limit` option of `catch_up` is now called `catch_up_limit`.
The `limit` option of `run_after_aggregate_save` has been removed without replacement.

before:

```yaml
patchlevel_event_sourcing:
    subscription:
        catch_up:
            limit: 10
        run_after_aggregate_save:
            groups: ['sync']
```
after:

```yaml
patchlevel_event_sourcing:
    subscription:
        sync:
            groups: ['sync']
            catch_up_limit: 10
```
If you run your tests with a worker-less setup, replace the options in `when@test` the same way.

### Retry Strategy

The deprecated `retry_strategy` option has been removed. Use `retry_strategies` instead.
The `Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy` service alias has been removed too,
inject the `RetryStrategyRepository` instead.

before:

```yaml
patchlevel_event_sourcing:
    subscription:
        retry_strategy:
            base_delay: 5
            delay_factor: 2
            max_attempts: 5
```
after:

```yaml
patchlevel_event_sourcing:
    subscription:
        retry_strategies:
            default:
                type: clock_based
                options:
                    base_delay: 5
                    delay_factor: 2
                    max_attempts: 5
```
### SubscriberHelper

The `SubscriberHelper` service is no longer registered, because the class has been removed from the library.
See the [event-sourcing upgrade guide](https://github.com/patchlevel/event-sourcing/blob/4.0.x/docs/UPGRADE-4.0.md#subscriberhelper-and-subscriberutil)
for the replacement.

## Store

### Store Type

The store type `dbal_aggregate` has been removed together with the `DoctrineDbalStore`.
The default store type is now `dbal_stream`.
The new store type `dbal_taggable` is available for the DCB feature.

before:

```yaml
patchlevel_event_sourcing:
    store:
        type: dbal_aggregate
```
after:

```yaml
patchlevel_event_sourcing:
    store:
        type: dbal_stream
```
:::danger
The `dbal_stream` store uses a different table structure than `dbal_aggregate`.
Migrate your events with the `migrate_to_new_store` option and the `event-sourcing:store:migrate` command
while you are still on 3.x, otherwise your events can no longer be read.
:::

### Read Only Mode

`read_only` is now only supported by the `dbal_stream` store.
For `dbal_taggable`, `in_memory` and `custom` an exception is thrown at container compile time.

### StoreMigrateCommand

The deprecated `Patchlevel\EventSourcingBundle\Command\StoreMigrateCommand` has been removed.
Use `Patchlevel\EventSourcing\Console\Command\StoreMigrateCommand` from the library instead.
The command name `event-sourcing:store:migrate` stays the same.

## Hydrator

### Legacy Hydrator

The legacy `MetadataHydrator` has been removed, the bundle always uses the `StackHydrator` now.
For this reason the `hydrator.enabled` option has been removed too.
Remove `hydrator: true` or `enabled` from your configuration, the other `hydrator` options stay the same.

before:

```yaml
patchlevel_event_sourcing:
    hydrator:
        enabled: true
        default_lazy: true
```
after:

```yaml
patchlevel_event_sourcing:
    hydrator:
        default_lazy: true
```

Together with the legacy hydrator, the guesser integration has been removed:
the `event_sourcing.hydrator.guesser` tag and the autoconfiguration for `Patchlevel\Hydrator\Guesser\Guesser` no longer exist.
Register your guesser in a hydrator extension with `$builder->addGuesser()` instead.
Services implementing `Patchlevel\Hydrator\Extension` are registered automatically.

### Cryptography

The root `cryptography` option has been removed. Use `hydrator.cryptography` instead.
The options `use_encrypted_field_name` and `fallback_to_field_name` have been removed without replacement.

before:

```yaml
patchlevel_event_sourcing:
    cryptography:
        algorithm: 'aes-256-gcm'
        use_encrypted_field_name: true
```
after:

```yaml
patchlevel_event_sourcing:
    hydrator:
        cryptography:
            algorithm: 'aes-256-gcm'
```
:::danger
Data encrypted with the legacy cryptography can no longer be decrypted.
Migrate your store and snapshots to the new format while you are still on 3.x,
see the [event-sourcing upgrade guide](https://github.com/patchlevel/event-sourcing/blob/4.0.x/docs/UPGRADE-4.0.md#sensitive-data).
:::

### Upcaster

The `UpcasterChain` service and the `Patchlevel\EventSourcing\Serializer\Upcast\Upcaster` alias have been removed.
Upcasters now implement `Patchlevel\Hydrator\Extension\Upcast\Upcaster` and are registered automatically.
The bundle adds them to the `UpcastExtension` of the hydrator.
Services tagged with `event_sourcing.upcaster` still work and are sorted by the `priority` attribute.

### SymfonyUuidNormalizer

The deprecated `Patchlevel\EventSourcingBundle\Normalizer\SymfonyUuidNormalizer` has been removed.
Use `Patchlevel\EventSourcingBundle\Normalizer\UidNormalizer` instead.

before:

```php
use Patchlevel\EventSourcingBundle\Normalizer\SymfonyUuidNormalizer;
use Symfony\Component\Uid\Uuid;

final class ProfileCreated
{
    public function __construct(
        #[SymfonyUuidNormalizer]
        public readonly Uuid $profileId,
    ) {
    }
}
```
after:

```php
use Patchlevel\EventSourcingBundle\Normalizer\UidNormalizer;
use Symfony\Component\Uid\Uuid;

final class ProfileCreated
{
    public function __construct(
        #[UidNormalizer]
        public readonly Uuid $profileId,
    ) {
    }
}
```
## Command Bus

The deprecated `aggregate_handlers` option has been removed. Use `command_bus` instead.

before:

```yaml
patchlevel_event_sourcing:
    aggregate_handlers:
        bus: command.bus
```
after:

```yaml
patchlevel_event_sourcing:
    command_bus:
        service: command.bus
```
## Value Resolver

`Patchlevel\EventSourcingBundle\ValueResolver\AggregateRootIdValueResolver` has been renamed to
`Patchlevel\EventSourcingBundle\ValueResolver\IdentifierValueResolver`.
It now resolves every controller argument that implements `Patchlevel\EventSourcing\Identifier\Identifier`.
You only have to change something if you reference the class directly, e.g. in `#[ValueResolver]`.
