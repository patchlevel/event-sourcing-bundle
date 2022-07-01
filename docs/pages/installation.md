# Installation

If you are not using a symfony [flex](https://github.com/symfony/flex) 
or the [recipes](https://flex.symfony.com/) for it, 
then you have to carry out a few installation steps by hand.

## Require package

The first thing to do is to install packet if it has not already been done.

```bash
composer require patchlevel/event-sourcing-bundle
```

!!! note

    how to install [composer](https://getcomposer.org/doc/00-intro.md)

## Enable bundle

Then we have to activate the bundle in the `config/bundles.php`:

```php
return [
    Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle::class => ['all' => true],
];
```

If you don't have `config/bundles.php` then you need to add the bundle in the kernel:

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle(),
        ];
    }
}
```

## Configuration file

Now you have to add a minimal configuration file here `config/packages/patchlevel_event_sourcing.yaml`.

```yaml
patchlevel_event_sourcing:
    aggregates: '%kernel.project_dir%/src'
    events: '%kernel.project_dir%/src'
    connection:
        url: '%env(EVENTSTORE_URL)%'
```

## Dotenv

Finally we have to fill the ENV variable with a connection url.

```dotenv
EVENTSTORE_URL=mysql://user:secret@localhost/app
```

!!! note

    You can find out more about what a connection url looks like [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url).

Now you can go back to [getting started](getting_started.md).