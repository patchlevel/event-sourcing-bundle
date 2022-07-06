# Projection

!!! info

    You can find out more about projection in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/projection/). 
    This documentation is limited to bundle integration.

With `projections` you can create your data optimized for reading.
projections can be adjusted, deleted or rebuilt at any time.
This is possible because the source of truth remains untouched
and everything can always be reproduced from the events.

The target of a projection can be anything.
Either a file, a relational database, a no-sql database like mongodb or an elasticsearch.

## Define Projection

In this example we are simply mapping hotel statistics:

```php
namespace App\Projection;

use App\Domain\Hotel\Event\HotelCreated;
use App\Domain\Hotel\Event\GuestIsCheckedIn;
use App\Domain\Hotel\Event\GuestIsCheckedOut;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection;

final class HotelProjection implements Projection
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    #[Handle(HotelCreated::class)]
    public function handleHotelCreated(Message $message): void
    {
        $event = $message->event();
    
        $this->db->insert(
            'hotel', 
            [
                'id' => $event->aggregateId(), 
                'name' => $event->hotelName(),
                'guests' => 0
            ]
        );
    }
    
    #[Handle(GuestIsCheckedIn::class)]
    public function handleGuestIsCheckedIn(Message $message): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests + 1 WHERE id = ?;',
            [$event->aggregateId()]
        );
    }
    
    #[Handle(GuestIsCheckedOut::class)]
    public function handleGuestIsCheckedOut(Message $message): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests - 1 WHERE id = ?;',
            [$event->aggregateId()]
        );
    }
    
    #[Create]
    public function create(): void
    {
        $this->db->executeStatement('CREATE TABLE IF NOT EXISTS hotel (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);');
    }

    #[Drop]
    public function drop(): void
    {
        $this->db->executeStatement('DROP TABLE IF EXISTS hotel;');
    }
}
```

If you have the symfony default service setting with `autowire`and `autoconfigure` enabled,
the projection is automatically recognized and registered at the `Projection` interface.
Otherwise you have to define the projection in the symfony service file:

```yaml
services:
    App\Projection\HotelProjection:
      tags:
        - event_sourcing.projection
```

## Projection commands

The bundle also provides a few commands to create, delete or rebuild projections:

```bash
bin/console event-sourcing:projection:create
bin/console event-sourcing:projection:drop
bin/console event-sourcing:projection:rebuild
```
