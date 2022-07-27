# Processor

The `processor` is a kind of [event bus](./event_bus.md) listener that can execute actions on certain events.

!!! info

    You can find out more about processor in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/processor/). 
    This documentation is limited to bundle integration.

## Usage

A process can be for example used to send an email when a guest is checked in:

```php
namespace App\Domain\Hotel\Listener;

use App\Domain\Hotel\Event\GuestIsCheckedIn;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendCheckInEmailListener implements Listener
{
    private MailerInterface $mailer;

    private function __construct(MailerInterface $mailer) 
    {
        $this->mailer = $mailer;
    }

    public function __invoke(Message $message): void
    {
        $event = $message->event();
    
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

If you have the symfony default service setting with `autowire`and `autoconfiger` enabled, 
the processor is automatically recognized and registered at the `Listener` interface. 
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

!!! note

    The `projection` listener has a priority of `-32`, 
    to do things after the projection, you have to be lower.
