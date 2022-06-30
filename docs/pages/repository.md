# Repository

!!! info

    You can find out more about repository in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/repository/). 
    This documentation is limited to bundle integration.

A `repository` takes care of storing and loading the `aggregates`.
The [design pattern](https://martinfowler.com/eaaCatalog/repository.html) of the same name is also used.

Every aggregate needs a repository to be stored.
And each repository is only responsible for one aggregate.

## Use repositories

Every aggregate that has been defined and registered automatically has a repository.
These repositories can also be auto-updated. 
To do this, you have to use the Typehint repository and structure the variable as follows. 
Aggregate name with a `Repository` suffix. For example we have the aggregate `hotel`,
then you can build the typhint as follows: `Patchlevel\EventSourcing\Repository\Repository $hotelRepository`.

```php
use Patchlevel\EventSourcing\Repository\Repository;

final class HotelController
{
    private Repository $hotelRepository;

    public function __construct(Repository $hotelRepository) 
    {
        $this->hotelRepository = $hotelRepository;
    }
    
    // ...
}
```

!!! note

    You can find out more about autowire [here](https://symfony.com/doc/current/service_container.html#binding-arguments-by-name-or-type)