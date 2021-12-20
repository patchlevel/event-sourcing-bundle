[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing-bundle/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing-bundle)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-bundle/v)](//packagist.org/packages/patchlevel/event-sourcing-bundle)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-bundle/license)](//packagist.org/packages/patchlevel/event-sourcing-bundle)

# Event-Sourcing-Bundle

a symfony integration of a small lightweight [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## Installation

```
composer require patchlevel/event-sourcing-bundle
```

If you don't use the symfony flex recipe for this bundle, you need to follow
this [installation documentation](docs/installation.md).

## Documentation

* [Repository](docs/repository.md)
* [Event Bus](docs/event_bus.md)
* [Processor](docs/processor.md)
* [Projection](docs/projection.md)
* [Snapshots](docs/snapshots.md)
* [Store](docs/store.md)
* [Pipeline](docs/pipeline.md)
* [Tools](docs/tools.md)

## Integration

* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)

## Getting Started

In our little getting started example, we manage hotels. We keep the example small, so we can only create hotels and let
guests check in and check out.

For this example we use following packages:

* [symfony/uid](https://symfony.com/doc/current/components/uid.html)
* [symfony/mailer](https://symfony.com/doc/current/mailer.html)

### Define some events

First we define the events that happen in our system.

A hotel can be created with a `name`:

```php
namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Component\Uid\Uuid;

final class HotelCreated extends AggregateChanged
{
    public static function raise(Uuid $id, string $hotelName): self 
    {
        return new self($id->toString(), ['hotelId' => $id->toString(), 'hotelName' => $hotelName]);
    }

    public function hotelId(): Uuid
    {
        return Uuid::fromString($this->aggregateId);
    }

    public function hotelName(): string
    {
        return $this->payload['hotelName'];
    }
}
```

A guest can check in by name:

```php
namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Component\Uid\Uuid;

final class GuestIsCheckedIn extends AggregateChanged
{
    public static function raise(Uuid $id, string $guestName): self 
    {
        return new self($id->toString(), ['guestName' => $guestName]);
    }

    public function guestName(): string
    {
        return $this->payload['guestName'];
    }
}
```

And also check out again:

```php
namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Component\Uid\Uuid;

final class GuestIsCheckedOut extends AggregateChanged
{
    public static function raise(Uuid $id, string $guestName): self 
    {
        return new self($id->toString(), ['guestName' => $guestName]);
    }

    public function guestName(): string
    {
        return $this->payload['guestName'];
    }
}
```

### Define aggregates

Next we need to define the aggregate. So the hotel and how the hotel should behave. We have also defined the `create`
, `checkIn` and `checkOut` methods accordingly. These events are thrown here and the state of the hotel is also changed.

```php
namespace App\Domain\Hotel;

use App\Domain\Hotel\Event\HotelCreated;
use App\Domain\Hotel\Event\GuestIsCheckedIn;
use App\Domain\Hotel\Event\GuestIsCheckedOut;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Symfony\Component\Uid\Uuid;

final class Hotel extends AggregateRoot
{
    private Uuid $id;
    private string $name;
    
    /**
     * @var list<string>
     */
    private array $guests;

    public function name(): string
    {
        return $this->name;
    }

    public function guests(): int
    {
        return $this->guests;
    }

    public static function create(Uuid $id, string $hotelName): self
    {
        $self = new self();
        $self->record(HotelCreated::raise($id, $hotelName));

        return $self;
    }

    public function checkIn(string $guestName): void
    {
        if (in_array($guestName, $this->guests, true)) {
            throw new GuestHasAlreadyCheckedIn($guestName);
        }
    
        $this->record(GuestIsCheckedIn::raise($this->id, $guestName));
    }
    
    public function checkOut(string $guestName): void
    {
        if (!in_array($guestName, $this->guests, true)) {
            throw new IsNotAGuest($guestName);
        }
    
        $this->record(GuestIsCheckedOut::raise($this->id, $guestName));
    }
    
    
    protected function apply(AggregateChanged $event): void
    {
        if ($event instanceof HotelCreated) {
            $this->id = $event->hotelId();
            $this->name = $event->hotelName();
            $this->guests = [];
            
            return;
        } 
        
        if ($event instanceof GuestIsCheckedIn) {
            $this->guests[] = $event->guestName();
            
            return;
        }
        
        if ($event instanceof GuestIsCheckedOut) {
            $this->guests = array_values(
                array_filter(
                    $this->guests,
                    fn ($name) => $name !== $event->guestName();
                )
            );
            
            return;
        }
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
```

> :book: You can find out more about aggregates and events [here](./docs/aggregate.md).

Next we have to make our aggregate known:

```yaml
patchlevel_event_sourcing:
  aggregates:
    hotel:
      class: App\Domain\Hotel\Hotel
```

### Define projections

So that we can see all the hotels on our website and also see how many guests are currently visiting the hotels, we need
a projection for it.

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

> :warning: autoconfigure need to be enabled, otherwise you need add the tag.

> :book: You can find out more about projections [here](./docs/projection.md).

### Processor

In our example we also want to send an email to the head office as soon as a guest is checked in.

```php
namespace App\Domain\Hotel\Listener;

use App\Domain\Hotel\Event\GuestIsCheckedIn;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendCheckInEmailListener implements Listener
{
    private MailerInterface $mailer;

    private function __construct(MailerInterface $mailer) 
    {
        $this->mailer = $mailer;
    }

    public function __invoke(AggregateChanged $event): void
    {
        if (!$event instanceof GuestIsCheckedIn) {
            return;
        }
        
        $email = (new Email())
            ->from('noreply@patchlevel.de')
            ->to('hq@patchlevel.de')
            ->subject('Guest is checked in')
            ->text(sprintf('A new guest named "%s" is checked in', $event->guestName()));
            
        $this->mailer->send($email);
    }
}
```

> :warning: autoconfigure need to be enabled, otherwise you need add the tag.

> :book: You can find out more about processor [here](./docs/processor.md).

### Database setup

So that we can actually write the data to a database, we need the associated schema and databases.

```bash
bin/console event-sourcing:database:create
bin/console event-sourcing:schema:create
bin/console event-sourcing:projection:create
```

### Usage

We are now ready to use the Event Sourcing System. We can load, change and save aggregates.

```php
namespace App\Controller;

use App\Domain\Hotel\Hotel;
use Patchlevel\EventSourcing\Repository\Repository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

final class HotelController
{
    private Repository $hotelRepository;

    public function __construct(Repository $hotelRepository) 
    {
        $this->hotelRepository = $hotelRepository;
    }

    /**
     * @Route("/create", methods={"POST"})
     */
    public function createAction(Request $request): JsonResponse
    {
        $name = $request->request->get('name'); // need validation!
        $id = Uuid::v4();
        
        $hotel = Hotel::create($id, $name);
        
        $this->hotelRepository->save($hotel);

        return new JsonResponse(['id' => $id->toString()]);
    }
    
    /**
     * @Route("/:id/check-in", methods={"POST"})
     */
    public function createAction(string $id, Request $request): JsonResponse
    {
        $id = Uuid::fromString($id);
        $guestName = $request->request->get('name'); // need validation!
        
        $hotel = $this->hotelRepository->load($id);
        $hotel->checkIn($guestName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse(['id' => $id->toString()]);
    }
    
    /**
     * @Route("/:id/check-out", methods={"POST"})
     */
    public function createAction(string $id, Request $request): JsonResponse
    {
        $id = Uuid::fromString($id);
        $guestName = $request->request->get('name'); // need validation!
        
        $hotel = $this->hotelRepository->load($id);
        $hotel->checkOut($guestName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse(['id' => $id->toString()]);
    }
}

```

> :book: todo: command bus

Consult the [bundle documentation](#documentation) 
or [library documentation](https://github.com/patchlevel/event-sourcing/#documentation) for more information. 
If you still have questions, feel free to create an issue for it :)

