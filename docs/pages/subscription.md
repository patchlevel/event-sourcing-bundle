# Subscription

With `projections` you can create your data optimized for reading.
projections can be adjusted, deleted or rebuilt at any time.
This is possible because the source of truth remains untouched
and everything can always be reproduced from the events.

The target of a projection can be anything.
Either a file, a relational database, a no-sql database like mongodb or an elasticsearch.

!!! info

    You can find out more about projection in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/projection/). 
    This documentation is limited to bundle integration.
    
## Define Projection

In this example we are simply mapping hotel statistics:

```php
namespace App\Projection;

use App\Domain\Hotel\Event\GuestIsCheckedIn;
use App\Domain\Hotel\Event\GuestIsCheckedOut;
use App\Domain\Hotel\Event\HotelCreated;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;

#[Projector(name: 'hotel')]
final class HotelProjection
{
    use ProjectorUtil;

    public function __construct(private Connection $db)
    {
    }

    /** @return list<array{id: string, name: string, guests: int}> */
    public function getHotels(): array
    {
        return $this->db->fetchAllAssociative("SELECT id, name, guests FROM {$this->table()};");
    }

    #[Handle(HotelCreated::class)]
    public function handleHotelCreated(Message $message): void
    {
        $event = $message->event();

        $this->db->insert(
            $this->table(),
            [
                'id' => $event->aggregateId(),
                'name' => $event->hotelName(),
                'guests' => 0,
            ],
        );
    }

    #[Handle(GuestIsCheckedIn::class)]
    public function handleGuestIsCheckedIn(Message $message): void
    {
        $event = $message->event();

        $this->db->executeStatement(
            "UPDATE {$this->table()} SET guests = guests + 1 WHERE id = ?;",
            [$event->aggregateId()],
        );
    }

    #[Handle(GuestIsCheckedOut::class)]
    public function handleGuestIsCheckedOut(Message $message): void
    {
        $event = $message->event();

        $this->db->executeStatement(
            "UPDATE {$this->table()} SET guests = guests - 1 WHERE id = ?;",
            [$event->aggregateId()],
        );
    }

    #[Create]
    public function create(): void
    {
        $this->db->executeStatement("CREATE TABLE IF NOT EXISTS {$this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);");
    }

    #[Drop]
    public function drop(): void
    {
        $this->db->executeStatement("DROP TABLE IF EXISTS {$this->table()};");
    }

    private function table(): string
    {
        return 'hotel_' . $this->projectorId();
    }
}
```
If you have the symfony default service setting with `autowire`and `autoconfigure` enabled,
the projection is automatically recognized and registered at the `Projector` interface.
Otherwise you have to define the projection in the symfony service file:

```yaml
services:
    App\Projection\HotelProjection:
      tags:
        - event_sourcing.projector
```
## Projection commands

The bundle also provides a few commands to create, delete or rebuild projections:

```bash
bin/console event-sourcing:projection:boot
bin/console event-sourcing:projection:run
bin/console event-sourcing:projection:teardown
bin/console event-sourcing:projection:remove
bin/console event-sourcing:projection:status
bin/console event-sourcing:projection:rebuild
```