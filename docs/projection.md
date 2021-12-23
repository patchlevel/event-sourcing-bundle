# Projection

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
use Patchlevel\EventSourcing\Projection\Projection;

final class HotelProjection implements Projection
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public static function getHandledMessages(): iterable
    {
        yield HotelCreated::class => 'applyHotelCreated';
        yield GuestIsCheckedIn::class => 'applyGuestIsCheckedIn';
        yield GuestIsCheckedOut::class => 'applyGuestIsCheckedOut';
    }

    public function applyHotelCreated(HotelCreated $event): void
    {
        $this->db->insert(
            'hotel', 
            [
                'id' => $event->aggregateId(), 
                'name' => $event->hotelName(),
                'guests' => 0
            ]
        );
    }
    
    public function applyGuestIsCheckedIn(GuestIsCheckedIn $event): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests + 1 WHERE id = ?;',
            [$event->aggregateId()]
        );
    }
    
    public function applyGuestIsCheckedOut(GuestIsCheckedOut $event): void
    {
        $this->db->executeStatement(
            'UPDATE hotel SET guests = guests - 1 WHERE id = ?;',
            [$event->aggregateId()]
        );
    }
    
    public function create(): void
    {
        $this->db->executeStatement('CREATE TABLE IF NOT EXISTS hotel (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);');
    }

    public function drop(): void
    {
        $this->db->executeStatement('DROP TABLE IF EXISTS hotel;');
    }
}
```

If you have the symfony default service setting with `autowire`and `autoconfiger` enabled,
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
