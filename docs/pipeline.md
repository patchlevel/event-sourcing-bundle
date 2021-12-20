## Pipeline

A store is immutable, i.e. it cannot be changed afterwards.
This includes both manipulating events and deleting them.

Instead, you can duplicate the store and manipulate the events in the process.
Thus the old store remains untouched and you can test the new store beforehand,
whether the migration worked.

Here you can find an example Symfony Command, how it can look like:

```php
<?php

declare(strict_types=1);

namespace App\Command;

use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Store\PipelineStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateStoreCommand extends Command
{
    private PipelineStore $oldStore;
    private PipelineStore $newStore;

    public function __construct(PipelineStore $oldStore, PipelineStore $newStore)
    {
        parent::__construct();

        $this->oldStore = $oldStore;
        $this->newStore = $newStore;
    }

    protected function configure(): void
    {
        $this->setName('app:migrate-store');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
    
        $pipeline = new Pipeline(
            new StoreSource($this->oldStore),
            new StoreTarget($this->newStore),
            [
                new ExcludeEventMiddleware([EmailChanged::class]), // remove a event type
                new ReplaceEventMiddleware(UserCreated::class, static function (UserCreated $event) {
                    return UserRegistered::raise(
                        $event->profileId(),
                        $event->email(),
                    );
                }), // replace a old event with another one
                new RecalculatePlayheadMiddleware(), // recalculate playhead (because we remove an event type)
            ]
        );

        $console->progressStart($pipeline->count());

        $pipeline->run(static function () use ($console): void {
            $console->progressAdvance();
        });

        $console->progressFinish();
        $console->success('finish');

        return 0;
    }
}
```

> :book: If you want to learn more about pipelines, 
> check out the [documentation](https://github.com/patchlevel/event-sourcing/blob/1.1.x/docs/pipeline.md).
