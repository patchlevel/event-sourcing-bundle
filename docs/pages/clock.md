# Clock

The clock is used to return the current time as DateTimeImmutable. 
There is a system clock and a frozen clock for test purposes.

!!! info

    You can find out more about clock in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/clock/). 
    This documentation is limited to bundle integration.

## Configuration

You can freeze time for your tests as follows:

```yaml
when@test:
    patchlevel_event_sourcing:
        clock:
            freeze: '2020-01-01 22:00:00'
```

!!! note

    If freeze is not set, then the system clock is used.