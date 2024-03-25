# Processor

The `processor` is a kind of [event bus](./event_bus.md) listener that can execute actions on certain events.

!!! info

    You can find out more about processor in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/processor/). 
    This documentation is limited to bundle integration.

!!! warning

    The following configuration option is only available with the default event bus. 
    If you use a different event bus, you will need to configure the listeners with that system. 
    You can find out more about this in the [event bus](./event_bus.md) documentation.

## Usage

A process can be for example used to send an email when a guest is checked in:

```php
namespace App\Domain\Hotel\Listener;

use App\Domain\Hotel\Event\GuestIsCheckedIn;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcingBundle\Attribute\AsListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsListener]
final class SendCheckInEmailListener
{
    private MailerInterface $mailer;

    private function __construct(MailerInterface $mailer) 
    {
        $this->mailer = $mailer;
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
the processor is automatically recognized and registered at the `AsProcessor` attribute. 
Otherwise you have to define the processor in the symfony service file:

```yaml
services:
    App\Domain\Hotel\Listener\SendCheckInEmailListener:
      tags:
        - event_sourcing.processor
```

## Priority

You can also determine the `priority` in which the processors are executed. 
The higher the priority, the earlier the processor is executed. 
You have to add the tag manually and specify the priority.

```php
namespace App\Domain\Hotel\Listener;

#[AsProcessor(priority: 16)]
final class SendCheckInEmailListener
{
    // ...
}
```

```yaml
services:
    App\Domain\Hotel\Listener\SendCheckInEmailListener:
      autoconfigure: false
      tags:
        - name: event_sourcing.processor
          priority: 16
```

!!! warning

    You have to deactivate the `autoconfigure` for this service, 
    otherwise the service will be added twice.
