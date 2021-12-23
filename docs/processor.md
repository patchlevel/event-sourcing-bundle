# Processor

The `processor` is a kind of [event bus](./event_bus.md) listener that can execute actions on certain events.
A process can be for example used to send an email when a guest is checked in:

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

If you have the symfony default service setting with `autowire`and `autoconfiger` enabled, 
the processor is automatically recognized and registered at the `Listener` interface. 
Otherwise you have to define the processor in the symfony service file:

```yaml
services:
    App\Domain\Hotel\Listener\SendCheckInEmailListener:
      tags:
        - event_sourcing.processor
```