# Getting Started

In our little getting started example, we manage hotels. We keep the example small, so we can only create hotels and let
guests check in and check out.

For this example we use following package:

* [symfony/mailer](https://symfony.com/doc/current/mailer.html)

## Installation

First of all, the bundle has to be installed and configured. 
If you haven't already done so, see the [installation introduction](installation.md).

## Define some events

First we define the events that happen in our system.

A hotel can be created with a `name`:

```php
namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        #[IdNormalizer(Uuid::class)]
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
final class GuestIsCheckedOut
{
    public function __construct(
        public readonly string $guestName
    ) {
    }
}
```

!!! note

    You can find out more about events [here](events.md).

## Define aggregates

Next we need to define the aggregate. So the hotel and how the hotel should behave. 
We have also defined the `create`, `checkIn` and `checkOut` methods accordingly. 
These events are thrown here and the state of the hotel is also changed.

```php
namespace App\Domain\Hotel;

use App\Domain\Hotel\Event\HotelCreated;
use App\Domain\Hotel\Event\GuestIsCheckedIn;
use App\Domain\Hotel\Event\GuestIsCheckedOut;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate(name: 'hotel')]
final class Hotel extends BasicAggregateRoot
{
    #[Id]
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
        $this->id = $event->id;
        $this->name = $event->hotelName;
        $this->guests = [];      
    }

    #[Apply]
    protected function applyGuestIsCheckedIn(GuestIsCheckedIn $event): void
    {
        $this->guests[] = $event->guestName;
    }
    
    #[Apply]
    protected function applyGuestIsCheckedOut(GuestIsCheckedOut $event): void
    {
        $this->guests = array_values(
            array_filter(
                $this->guests,
                fn ($name) => $name !== $event->guestName;
            )
        );
    }
}
```

!!! note

    You can find out more about aggregates [here](aggregate.md).

## Define projections

So that we can see all the hotels on our website 
and also see how many guests are currently visiting the hotels, 
we need a projection for it.

```php
namespace App\Projection;

use App\Domain\Hotel\Event\HotelCreated;
use App\Domain\Hotel\Event\GuestIsCheckedIn;
use App\Domain\Hotel\Event\GuestIsCheckedOut;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;

#[Projector('hotel')]
final class HotelProjection
{
    use ProjectorUtil;

    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }
    
    /**
     * @return list<array{id: string, name: string, guests: int}>
     */
    public function getHotels(): array 
    {
        return $this->db->fetchAllAssociative("SELECT id, name, guests FROM ${this->table()};")
    }

    #[Subscribe(HotelCreated::class)]
    public function handleHotelCreated(Message $message): void
    {
        $this->db->insert(
            $this->table(), 
            [
                'id' => $message->aggregateId(), 
                'name' => $message->event()->hotelName(),
                'guests' => 0
            ]
        );
    }
    
    #[Subscribe(GuestIsCheckedIn::class)]
    public function handleGuestIsCheckedIn(Message $message): void
    {
        $this->db->executeStatement(
            "UPDATE ${this->table()} SET guests = guests + 1 WHERE id = ?;",
            [$message->aggregateId()]
        );
    }
    
    #[Subscribe(GuestIsCheckedOut::class)]
    public function handleGuestIsCheckedOut(Message $message): void
    {
        $this->db->executeStatement(
            "UPDATE ${this->table()} SET guests = guests - 1 WHERE id = ?;",
            [$message->aggregateId()]
        );
    }
    
    #[Setup]
    public function create(): void
    {
        $this->db->executeStatement("CREATE TABLE IF NOT EXISTS ${this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);");
    }

    #[Teardown]
    public function drop(): void
    {
        $this->db->executeStatement("DROP TABLE IF EXISTS ${this->table()};");
    }
    
    private function table(): string 
    {
        return sprintf('%s_%s', $this->projectionName(), $this->projectionVersion());
    }
}
```

!!! warning

    autoconfigure need to be enabled, otherwise you need add the `event_sourcing.projector` tag.

!!! note

    You can find out more about projections [here](projection.md).

## Processor

In our example we also want to send an email to the head office as soon as a guest is checked in.

```php
namespace App\Domain\Hotel\Listener;

use App\Domain\Hotel\Event\GuestIsCheckedIn;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendCheckInEmailListener extends Subscriber
{
    private function __construct(
        private readonly MailerInterface $mailer
    ) 
    {
    }

    #[Subscribe(GuestIsCheckedIn::class)]
    public function onGuestIsCheckedIn(Message $message): void
    {
        $event = $message->event();
        
        $email = (new Email())
            ->from('noreply@patchlevel.de')
            ->to('hq@patchlevel.de')
            ->subject('Guest is checked in')
            ->text(sprintf('A new guest named "%s" is checked in', $event->guestName()));
            
        $this->mailer->send($email);
    }
}
```

!!! warning

    autoconfigure need to be enabled, otherwise you need add the `event_sourcing.processor` tag.

!!! note

    You can find out more about processor [here](processor.md).

## Database setup

So that we can actually write the data to a database, we need the associated schema and databases.

```bash
bin/console event-sourcing:database:create
bin/console event-sourcing:schema:create
bin/console event-sourcing:projection:boot
```

### Usage

We are now ready to use the Event Sourcing System. We can load, change and save aggregates.

```php
namespace App\Controller;

use App\Domain\Hotel\Hotel;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class HotelController
{
    /** @var Repository<Hotel> */
    private Repository $hotelRepository;
    private HotelProjection $hotelProjection;

    public function __construct(
        RepositoryManager $repositoryManager,
        HotelProjection $hotelProjection
    ) {
        $this->hotelRepository = $repositoryManager->get(Hotel::class);
        $this->hotelProjection = $hotelProjection;
    }
    
    #[Route("/", methods:["GET"])]
    public function listAction(): JsonResponse
    {
        return new JsonResponse(
            $this->hotelProjection->getHotels()
        );
    }

    #[Route("/create", methods:["POST"])]
    public function createAction(Request $request): JsonResponse
    {
        $hotelName = $request->request->get('name'); // need validation!
        $id = Uuid::v7();
        
        $hotel = Hotel::create($id, $hotelName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse(['id' => $id->toString()]);
    }
    
    #[Route("/:id/check-in", methods:["POST"])]
    public function checkInAction(string $id, Request $request): JsonResponse
    {
        $id = Uuid::fromString($id);
        $guestName = $request->request->get('name'); // need validation!
        
        $hotel = $this->hotelRepository->load($id);
        $hotel->checkIn($guestName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse();
    }

     #[Route("/:id/check-out", methods:["POST"])]
    public function checkOutAction(string $id, Request $request): JsonResponse
    {
        $id = Uuid::fromString($id);
        $guestName = $request->request->get('name'); // need validation!
        
        $hotel = $this->hotelRepository->load($id);
        $hotel->checkOut($guestName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse();
    }
}
```

!!! note

    You can also use a [command bus](event_bus.md).
