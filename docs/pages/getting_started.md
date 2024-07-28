# Getting Started

In our little getting started example, we manage hotels.
We keep the example small, so we can only create hotels and let guests check in and check out.

For this example we use [symfony/mailer](https://symfony.com/doc/current/mailer.html).

!!! info

    First of all, the bundle has to be installed and configured.
    If you haven't already done so, see the [installation introduction](installation.md).
    
## Define some events

First we define the events that happen in our system.

A hotel can be created with a `name` and an `id`:

```php
namespace App\Hotel\Domain\Event;

use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        #[IdNormalizer]
        public readonly Uuid $id,
        public readonly string $hotelName,
    ) {
    }
}
```
A guest can check in by `name`:

```php
namespace App\Hotel\Domain\Event;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('hotel.guest_is_checked_in')]
final class GuestIsCheckedIn
{
    public function __construct(
        public readonly string $guestName,
    ) {
    }
}
```
And also check out again:

```php
namespace App\Hotel\Domain\Event;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('hotel.guest_is_checked_out')]
final class GuestIsCheckedOut
{
    public function __construct(
        public readonly string $guestName,
    ) {
    }
}
```
!!! note

    You can find out more about events in the [library](https://event-sourcing.patchlevel.io/latest/events/).
    
## Define aggregates

Next we need to define the hotel aggregate.
How you can interact with it, which events happen and what the business rules are.
For this we create the methods `create`, `checkIn` and `checkOut`.
In these methods the business checks are made and the events are recorded.
Last but not least, we need the associated apply methods to change the state.

```php
namespace App\Hotel\Domain;

use App\Hotel\Domain\Event\GuestIsCheckedIn;
use App\Hotel\Domain\Event\GuestIsCheckedOut;
use App\Hotel\Domain\Event\HotelCreated;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

use function array_filter;
use function array_values;
use function in_array;

#[Aggregate(name: 'hotel')]
final class Hotel extends BasicAggregateRoot
{
    #[Id]
    private Uuid $id;
    private string $name;

    /** @var list<string> */
    private array $guests;

    public function name(): string
    {
        return $this->name;
    }

    public function guests(): array
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

        $this->recordThat(new GuestIsCheckedIn($guestName));
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
                static fn ($name) => $name !== $event->guestName,
            ),
        );
    }
}
```
!!! note

    You can find out more about aggregates in the [library](https://event-sourcing.patchlevel.io/latest/aggregate/).
    
## Define projections

So that we can see all the hotels on our website and also see how many guests are currently visiting the hotels,
we need a projection for it. To create a projection we need a projector.
Each projector is then responsible for a specific projection.

```php
namespace App\Hotel\Infrastructure\Projection;

use App\Hotel\Domain\Event\GuestIsCheckedIn;
use App\Hotel\Domain\Event\GuestIsCheckedOut;
use App\Hotel\Domain\Event\HotelCreated;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;

#[Projector('hotel')]
final class HotelProjection
{
    use SubscriberUtil;

    public function __construct(private Connection $db)
    {
    }

    /** @return list<array{id: string, name: string, guests: int}> */
    public function getHotels(): array
    {
        return $this->db->fetchAllAssociative("SELECT id, name, guests FROM {$this->table()};");
    }

    #[Subscribe(HotelCreated::class)]
    public function handleHotelCreated(HotelCreated $event): void
    {
        $this->db->insert(
            $this->table(),
            [
                'id' => $event->id->toString(),
                'name' => $event->hotelName,
                'guests' => 0,
            ],
        );
    }

    #[Subscribe(GuestIsCheckedIn::class)]
    public function handleGuestIsCheckedIn(Uuid $hotelId): void
    {
        $this->db->executeStatement(
            "UPDATE {$this->table()} SET guests = guests + 1 WHERE id = ?;",
            [$hotelId->toString()],
        );
    }

    #[Subscribe(GuestIsCheckedOut::class)]
    public function handleGuestIsCheckedOut(Uuid $hotelId): void
    {
        $this->db->executeStatement(
            "UPDATE {$this->table()} SET guests = guests - 1 WHERE id = ?;",
            [$hotelId->toString()],
        );
    }

    #[Setup]
    public function create(): void
    {
        $this->db->executeStatement("CREATE TABLE IF NOT EXISTS {$this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR, guests INTEGER);");
    }

    #[Teardown]
    public function drop(): void
    {
        $this->db->executeStatement("DROP TABLE IF EXISTS {$this->table()};");
    }

    private function table(): string
    {
        return 'projection_' . $this->subscriberId();
    }
}
```
!!! warning

    autoconfigure need to be enabled, otherwise you need add the `event_sourcing.subscriber` tag.
    
!!! note

    You can find out more about projections in the [library](https://event-sourcing.patchlevel.io/latest/subscription/).
    
## Processor

In our example we also want to send an email to the head office as soon as a guest is checked in.

```php
namespace App\Hotel\Application\Processor;

use App\Hotel\Domain\Event\GuestIsCheckedIn;
use Patchlevel\EventSourcing\Attribute\Processor;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use function sprintf;

#[Processor('admin_emails')]
final class SendCheckInEmailListener
{
    private function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    #[Subscribe(GuestIsCheckedIn::class)]
    public function onGuestIsCheckedIn(GuestIsCheckedIn $event): void
    {
        $email = (new Email())
            ->from('noreply@patchlevel.de')
            ->to('hq@patchlevel.de')
            ->subject('Guest is checked in')
            ->text(sprintf('A new guest named "%s" is checked in', $event->guestName));

        $this->mailer->send($email);
    }
}
```
!!! warning

    autoconfigure need to be enabled, otherwise you need add the `event_sourcing.subscriber` tag.
    
!!! note

    You can find out more about processor in the [library](https://event-sourcing.patchlevel.io/latest/subscription/)
    
## Database setup

So that we can actually write the data to a database, we need the associated schema and databases.

```bash
bin/console event-sourcing:database:create
bin/console event-sourcing:schema:create
bin/console event-sourcing:subscription:setup
```
!!! note

    You can find out more about the cli in the [library](https://event-sourcing.patchlevel.io/latest/cli/).
    
### Usage

We are now ready to use the Event Sourcing System. We can load, change and save aggregates.

```php
namespace App\Hotel\Infrastructure\Controller;

use App\Hotel\Domain\Hotel;
use App\Hotel\Infrastructure\Projection\HotelProjection;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class HotelController
{
    /** @var Repository<Hotel> */
    private Repository $hotelRepository;

    public function __construct(
        RepositoryManager $repositoryManager,
        private HotelProjection $hotelProjection,
    ) {
        $this->hotelRepository = $repositoryManager->get(Hotel::class);
    }

    #[Route('/', methods:['GET'])]
    public function listAction(): JsonResponse
    {
        return new JsonResponse(
            $this->hotelProjection->getHotels(),
        );
    }

    #[Route('/create', methods:['POST'])]
    public function createAction(Request $request): JsonResponse
    {
        $hotelName = $request->request->get('name'); // need validation!
        $id = Uuid::v7();

        $hotel = Hotel::create($id, $hotelName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse(['id' => $id->toString()]);
    }

    #[Route('/{hotelId}/check-in', methods:['POST'])]
    public function checkInAction(Uuid $hotelId, Request $request): JsonResponse
    {
        $guestName = $request->request->get('name'); // need validation!

        $hotel = $this->hotelRepository->load($hotelId);
        $hotel->checkIn($guestName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse();
    }

    #[Route('/{hotelId}/check-out', methods:['POST'])]
    public function checkOutAction(Uuid $hotelId, Request $request): JsonResponse
    {
        $guestName = $request->request->get('name'); // need validation!

        $hotel = $this->hotelRepository->load($hotelId);
        $hotel->checkOut($guestName);
        $this->hotelRepository->save($hotel);

        return new JsonResponse();
    }
}
```
## Result

!!! success

    We have successfully implemented and used event sourcing.
    
    Feel free to browse further in the documentation for more detailed information. 
    If there are still open questions, create a ticket on Github and we will try to help you.
    
!!! note

    This documentation is limited to the bundle integration.
    You should also read the [library documentation](https://event-sourcing.patchlevel.io/latest/).
    