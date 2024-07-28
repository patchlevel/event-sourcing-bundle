# Usage

Here you will find some examples of how to use the bundle.
But we provide only examples for specific symfo

!!! info

    You can find out more about event sourcing in the library 
    [documentation](https://event-sourcing.patchlevel.io/latest/). 
    This documentation is limited to bundle integration and configuration.
    
## Repository

You can access the specific repositories using the `RepositoryManager::get`. Or inject directly the right repository via
argument name injection. For our aggregate `Hotel` it would be `$hotelRepository`.

```php
namespace App\Hotel\Infrastructure\Controller;

use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Repository\Repository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class HotelController
{
    public function __construct(
        /** @var Repository<Hotel> */
        private readonly Repository $hotelRepository,
    ) {
    }

    public function doStuffAction(Uuid $hotelId): Response
    {
        $hotel = $this->hotelRepository->load($hotelId);

        $hotel->doStuff();

        $hotelRepository->save($hotel);

        return new Response();
    }
}
```
## Subscriber

A subscriber can be used to send an email when a guest is checked in:

```php
namespace App\Hotel\Application\Subscriber;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber('send_check_in_email', RunMode::FromNow)]
class SendCheckInEmailSubscriber
{
    // ...
}
```
If you have the symfony default service setting with `autowire`and `autoconfiger` enabled,
the subscriber is automatically recognized and registered at the `Subscriber` attribute.
Otherwise you have to define the subscriber in the symfony service file:

```yaml
services:
    App\Hotel\Application\Subscriber\SendCheckInEmailSubscriber:
      tags:
        - event_sourcing.subscriber
```
## Event Bus Listener

A process can be for example used to send an email when a guest is checked in:

```php
namespace App\Hotel\Application\Listener;

use App\Hotel\Domain\Event\GuestIsCheckedIn;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcingBundle\Attribute\AsListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use function sprintf;

#[AsListener]
final class SendCheckInEmailListener
{
    private function __construct(private MailerInterface $mailer)
    {
    }

    #[Subscribe(GuestIsCheckedIn::class)]
    public function __invoke(Message $message): void
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
If you have the symfony default service setting with `autowire`and `autoconfiger` enabled,
the listener is automatically recognized and registered at the `AsListener` attribute.
Otherwise you have to define the listener in the symfony service file:

```yaml
services:
    App\Hotel\Application\Listener\SendCheckInEmailListener:
      tags:
        - event_sourcing.listener
```
### Priority

You can also determine the `priority` in which the listeners are executed.
The higher the priority, the earlier the listener is executed.
You have to add the tag manually and specify the priority.

```php
namespace App\Hotel\Application\Listener;

#[AsListener(priority: 16)]
final class SendCheckInEmailListener
{
    // ...
}
```
```yaml
services:
    App\Hotel\Application\Listener\SendCheckInEmailListener:
      autoconfigure: false
      tags:
        - name: event_sourcing.listener
          priority: 16
```
!!! warning

    You have to deactivate the `autoconfigure` for this service, 
    otherwise the service will be added twice.
    
## Normalizer

This bundle adds more Symfony specific normalizers in addition to the existing built-in normalizers.

!!! note

    You can find the other build-in normalizers [here](https://event-sourcing.patchlevel.io/latest/normalizer/#built-in-normalizer)
    
### Uuid

With the `Uuid` Normalizer, as the name suggests, you can convert Symfony Uuid objects to a string and back again.

```php
use Patchlevel\EventSourcingBundle\Normalizer\SymfonyUuidNormalizer;
use Symfony\Component\Uid\Uuid;

final class DTO
{
    #[SymfonyUuidNormalizer]
    public Uuid $id;
}
```
!!! warning

    The symfony uuid don't implement the `AggregateId` interface, so it can be used as aggregate id.
    
!!! tip

    Use the `Uuid` implementation and `IdNormalizer` from the library to use it as an aggregate id.
    
## Upcasting

```php
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;

final class ProfileCreatedEmailLowerCastUpcaster implements Upcaster
{
    public function __invoke(Upcast $upcast): Upcast
    {
        // ignore if other event is processed
        if ($upcast->eventName !== 'profile_created') {
            return $upcast;
        }

        return $upcast->replacePayloadByKey('email', strtolower($upcast->payload['email']));
    }
}
```
If you have the symfony default service setting with `autowire`and `autoconfigure` enabled,
the upcaster is automatically recognized and registered at the `Upcaster` interface.
Otherwise you have to define the upcaster in the symfony service file:

```yaml
services:
    App\Upcaster\ProfileCreatedEmailLowerCastUpcaster:
      tags:
        - event_sourcing.upcaster
```
## Message Decorator

We want to add the header information which user was logged in when this event was generated.

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class LoggedUserDecorator implements MessageDecorator
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(Message $message): Message
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return $message;
        }

        return $message->withHeader(new UserHeader($token->getUsername()));
    }
}
```
If you have the symfony default service setting with `autowire`and `autoconfigure` enabled,
the message decorator is automatically recognized and registered at the `MessageDecorator` interface.
Otherwise you have to define the message decorator in the symfony service file:

```yaml
services:
  App\Message\Decorator\LoggedUserDecorator:
    tags:
      - event_sourcing.message_decorator
```