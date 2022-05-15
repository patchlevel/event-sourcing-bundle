[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing-bundle/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing-bundle)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-bundle/v)](//packagist.org/packages/patchlevel/event-sourcing-bundle)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-bundle/license)](//packagist.org/packages/patchlevel/event-sourcing-bundle)

# Event-Sourcing-Bundle

A lightweight but also all-inclusive event sourcing bundle 
with a focus on developer experience and based on doctrine dbal.
This bundle is a [symfony](https://symfony.com/) integration 
for [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](docs/snapshots.md) system to quickly rebuild the aggregates
* [Pipeline](docs/pipeline.md) to build new [projections](docs/projection.md) or to migrate events
* [Scheme management](docs/store.md) and [doctrine migration](docs/store.md) support
* Dev [tools](docs/tools.md) such as a realtime event watcher
* Built in [cli commands](docs/cli.md) with [symfony](https://symfony.com/)

## Installation

```bash
composer require patchlevel/event-sourcing-bundle
```

> :warning: If you don't use the symfony flex recipe for this bundle, you need to follow
this [installation documentation](docs/installation.md).

## Documentation

We recommend reading the documentation for the [library](https://github.com/patchlevel/event-sourcing) first, 
as this documentation only deals with bundle integration.

* [Aggregate](docs/aggregate.md)
* [Repository](docs/repository.md)
* [Event Bus](docs/event_bus.md)
* [Processor](docs/processor.md)
* [Projection](docs/projection.md)
* [Snapshots](docs/snapshots.md)
* [Store](docs/store.md)
* [Pipeline](docs/pipeline.md)
* [Tools](docs/tools.md)
* [CLI](docs/cli.md)

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

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcingBundle\Normalizer\UuidNormalizer;
use Symfony\Component\Uid\Uuid;

#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        #[Normalize(UuidNormalizer::class)]
        public readonly Uuid $id, 
        public readonly string $hotelName
    ) {
    }
}
```

A guest can check in by name:

```php
namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('hotel.guest_is_checked_in')]
final class GuestIsCheckedIn
{
    public function __construct(
        public readonly string $guestName
    ) {
    }
}
```

And also check out again:

```php
namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('hotel.guest_is_checked_out')]
final class GuestIsCheckedOut extends AggregateChanged
{
    public function __construct(
        public readonly string $guestName
    ) {
    }
}
```

### Define aggregates

Next we need to define the aggregate. So the hotel and how the hotel should behave. 
We have also defined the `create`, `checkIn` and `checkOut` methods accordingly. 
These events are thrown here and the state of the hotel is also changed.

```php
namespace App\Domain\Hotel;

use App\Domain\Hotel\Event\HotelCreated;
use App\Domain\Hotel\Event\GuestIsCheckedIn;
use App\Domain\Hotel\Event\GuestIsCheckedOut;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Symfony\Component\Uid\Uuid;

#[Aggregate(name: 'hotel')]
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
        $self->recordThat(new HotelCreated($id, $hotelName));

        return $self;
    }

    public function checkIn(string $guestName): void
    {
        if (in_array($guestName, $this->guests, true)) {
            throw new GuestHasAlreadyCheckedIn($guestName);
        }
    
        $this->recordThat(new GuestIsCheckedIn(guestName));
    }
    
    public function checkOut(string $guestName): void
    {
        if (!in_array($guestName, $this->guests, true)) {
            throw new IsNotAGuest($guestName);
        }
    
        $this->recordThat(new GuestIsCheckedOut($guestName));
    }
    
    #[Apply]
    protected function applyHotelCreated(HotelCreated $event): void
    {
        $this->id = $event->hotelId();
        $this->name = $event->hotelName();
        $this->guests = [];      
    }

    #[Apply]
    protected function applyGuestIsCheckedIn(GuestIsCheckedIn $event): void
    {
        $this->guests[] = $event->guestName();
    }
    
    #[Apply]
    protected function applyGuestIsCheckedOut(GuestIsCheckedOut $event): void
    {
        $this->guests = array_values(
            array_filter(
                $this->guests,
                fn ($name) => $name !== $event->guestName();
            )
        );
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
```

> :warning: The attribute variant is only available since v1.2. Switch to the v1.1 branch to read the older documentation.

> :book: You can find out more about aggregates [here](./docs/aggregate.md).

### Define projections

So that we can see all the hotels on our website 
and also see how many guests are currently visiting the hotels, 
we need a projection for it.

```php
namespace App\Projection;

use App\Domain\Hotel\Event\HotelCreated;
use App\Domain\Hotel\Event\GuestIsCheckedIn;
use App\Domain\Hotel\Event\GuestIsCheckedOut;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Projection\Projection;

final class HotelProjection
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function handleHotelCreated(HotelCreated $event): void
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

> :warning: autoconfigure need to be enabled, otherwise you need add the `event_sourcing.projection` tag.

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

> :warning: autoconfigure need to be enabled, otherwise you need add the `event_sourcing.processor` tag.

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
use Patchlevel\EventSourcingBundle\RepositoryManager;use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

final class HotelController
{
    private Repository $hotelRepository;

    public function __construct(RepositoryManager $repositoryManager) 
    {
        $this->hotelRepository = $repositoryManager->get(Hotel::class);
    }

    #[Route("/create", methods:["POST"])]
    public function createAction(Request $request): JsonResponse
    {
        $name = $request->request->get('name'); // need validation!
        $id = Uuid::v4();
        
        $hotel = Hotel::create($id, $name);
        
        $this->hotelRepository->save($hotel);

        return new JsonResponse(['id' => $id->toString()]);
    }
    
    #[Route("/:id/check-in", methods:["POST"])]
    public function createAction(string $id, Request $request): JsonResponse
    {
        $id = Uuid::fromString($id);
        $guestName = $request->request->get('name'); // need validation!
        
        $hotel = $this->hotelRepository->load($id);
        $hotel->checkIn($guestName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse(['id' => $id->toString()]);
    }

     #[Route("/:id/check-out", methods:["POST"])]
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

> :book: You can also use a [command bus](docs/event_bus.md).

Consult the [bundle documentation](#documentation) 
or [library documentation](https://github.com/patchlevel/event-sourcing/#documentation) for more information. 
If you still have questions, feel free to create an issue for it :)

