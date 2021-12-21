# Projection

With `projections` you can create your data optimized for reading.
projections can be adjusted, deleted or rebuilt at any time.
This is possible because the source of truth remains untouched
and everything can always be reproduced from the events.

The target of a projection can be anything.
Either a file, a relational database, a no-sql database like mongodb or an elasticsearch.

## Projection commands

```bash
bin/console event-sourcing:projection:create
bin/console event-sourcing:projection:drop
bin/console event-sourcing:projection:rebuild
```
