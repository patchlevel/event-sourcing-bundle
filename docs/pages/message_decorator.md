# Message Decorator

There are usecases where you want to add some extra context to your events like metadata which is not directly relevant
for your domain. With `MessageDecorator` we are providing a solution to add this metadata to your events. The metadata
will also be persisted in the database and can be retrieved later on.

!!! info

    You can find out more about message decorator in the library 
    [documentation](https://event-sourcing.patchlevel.io/latest/message_decorator/). 
    This documentation is limited to bundle integration.

## Usage

We want to add the header information which user was logged in when this event was generated.

```php
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class LoggedUserDecorator implements MessageDecorator
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage
    ) {}

    public function __invoke(Message $message): Message
    {
        $token = $this->tokenStorage->getToken();
        
        if (!$token) {
            return;
        }
        
        return $message->withCustomHeader('user', $token->getUserIdentifier());
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