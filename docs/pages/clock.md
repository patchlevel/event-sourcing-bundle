# Clock

The clock is used to return the current time as DateTimeImmutable. 
There is a system clock and a frozen clock for test purposes.

!!! info

    You can find out more about clock in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/clock/). 
    This documentation is limited to bundle integration.

## Testing

You can freeze the clock for testing purposes:

```yaml
when@test:
    patchlevel_event_sourcing:
        clock:
            freeze: '2020-01-01 22:00:00'
```

!!! note

    If freeze is not set, then the system clock is used.

## PSR-20

You can also use your own implementation of your choice. 
They only have to implement the interface of the [psr-20](https://www.php-fig.org/psr/psr-20/). 
You can then specify this service here:

```yaml
patchlevel_event_sourcing:
    clock:
        service: 'my_own_clock_service'
```

## Symfony Clock

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
