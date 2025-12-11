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
use Patchlevel\EventSourcingBundle\PatchlevelEventSourcingBundle;

return [
    PatchlevelEventSourcingBundle::class => ['all' => true],
];
```
## Configuration file

Now you have to add following recommended configuration file here `config/packages/patchlevel_event_sourcing.yaml`.

```yaml
patchlevel_event_sourcing:
    aggregates: '%kernel.project_dir%/src'
    events: '%kernel.project_dir%/src'
    connection:
      url: '%env(EVENTSTORE_URL)%'
      provide_dedicated_connection: true
    store:
      type: dbal_stream
      # if you are using doctrine bundle you should enable this
      #merge_orm_schema: true
    command_bus:
      service: messenger.default_bus
    query_bus:
      service: messenger.default_bus
    subscription:
      gap_detection: ~

    # enable this if you want to use sensitive data encryption
    #cryptography: ~ 
    #  use_encrypted_field_name: true

when@dev:
  patchlevel_event_sourcing:
    subscription:
      catch_up: true
      throw_on_error: true
      run_after_aggregate_save: true
      rebuild_after_file_change: true
      auto_setup: true

when@test:
  patchlevel_event_sourcing:
    subscription:
      store:
        type: 'static_in_memory'
      catch_up: true
      throw_on_error: true
      run_after_aggregate_save: true
```
## Dotenv

Finally, we have to fill the ENV variable with a connection url.

```dotenv
EVENTSTORE_URL="pdo-pgsql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```
!!! note

    You can find out more about what a connection url looks like [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url).
    
## Database with Docker

If you are using docker, you can use the following `compose.yaml` file to start a postgres database.

```yaml
services:
  eventstore:
    image: postgres:${POSTGRES_VERSION:-16}-alpine
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-app}
      # You should definitely change the password in production
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-!ChangeMe!}
      POSTGRES_USER: ${POSTGRES_USER:-app}
    volumes:
      - eventstore_data:/var/lib/postgresql/data:rw
      # You may use a bind-mounted host directory instead, so that it is harder to accidentally remove the volume and lose all your data!
      # - ./docker/db/data:/var/lib/postgresql/data:rw

volumes:
  eventstore_data:
```
And for development, you can add a `compose.override.yaml` file to expose the port.

```yaml
services:
  eventstore:
    ports:
      - "5432"
```
## Symfony CLI

If you are using the [symfony cli](https://symfony.com/download),
you can configure that the database is started automatically if you start the server.
For this you have to add the following configuration to the `.symfony.local.yaml` file.

```yaml
workers:
  docker_compose: ~
```
!!! success

    You have successfully installed the bundle. Now you can start with the [quickstart](./getting_started.md) to get a feeling for the bundle.
    