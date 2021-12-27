# Pipeline

A store is immutable, i.e. it cannot be changed afterwards. This includes both manipulating events and deleting them.

Instead, you can duplicate the store and manipulate the events in the process. Thus the old store remains untouched and
you can test the new store beforehand, whether the migration worked.

In this example the event `PrivacyAdded` is removed and the event `OldVisited` is replaced by `NewVisited`:

```php
namespace App\Command;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateUserCommand extends Command
{
    protected static $defaultName = 'app:event-sourcing:migrate-db-v2';
    
    private Store $oldStore;
    private Connection $newConnection;
    private array $aggregates;

    public function __construct(Store $oldStore, Connection $newConnection, array $aggregates) 
    {
        $this->oldStore = $oldStore;
        $this->newConnection = $newConnection;
        $this->aggregates = $aggregates;
    
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $newStore = new MultiTableStore($this->newConnection, $this->aggregates);
    
        $pipeline = new Pipeline(
            new StoreSource($oldStore),
            new StoreTarget($newStore),
            [
                new ExcludeEventMiddleware([PrivacyAdded::class]),
                new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
                    return NewVisited::raise($oldVisited->profileId());
                }),
                new RecalculatePlayheadMiddleware(),
            ]
        );
        
        $console->progressStart($pipeline->count());

        $pipeline->run(static function () use ($console): void {
            $console->progressAdvance();
        });

        $console->progressFinish();
        $console->success('finish');

        return Command::SUCCESS;
    }
}
```

The whole thing just has to be plugged together.

```yaml
services:
  App\Command\CreateUserCommand:
    arguments:
      aggregates: '%event_sourcing.aggregates%'
      newConnection: '@doctrine.dbal.new_connection'
```

> :book: If you have the doctrine bundle for the dbal connections, 
> then you can [autowiren](https://symfony.com/bundles/DoctrineBundle/current/configuration.html#autowiring-multiple-connections) it.


