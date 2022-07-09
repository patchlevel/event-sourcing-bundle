# Repository

A `repository` takes care of storing and loading the `aggregates`.
The [design pattern](https://martinfowler.com/eaaCatalog/repository.html) of the same name is also used.

Every aggregate needs a repository to be stored.
And each repository is only responsible for one aggregate.

!!! info

    You can find out more about repository in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/repository/). 
    This documentation is limited to bundle integration.

## Use repositories

You can access the specific repositories using the `RepositoryManager`.

```php
use Patchlevel\EventSourcing\Repository\RepositoryManager;

final class HotelController
{    
    public function doStuffAction(RepositoryManager $repositoryManager): Response
    {
        $hotelRepository = $repositoryManager->get(Hotel::class);
        $hotel = $hotelRepository->load('1');
        
        $hotel->doStuff();
        
        $hotelRepository->save($hotel);
        
        // ...
    }
}
```

## Custom Repositories

In clean code you want to have explicit type hints for the repositories
so that you don't accidentally use the wrong repository.
It would also help in frameworks with a dependency injection container,
as this allows the services to be autowired.
However, you cannot inherit from our repository implementations.
Instead, you just have to wrap these repositories.
This also gives you more type security.

```php
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

class HotelRepository 
{
    /** @var Repository<Hotel>  */
    private Repository $repository;

    public function __construct(RepositoryManager $repositoryManager) 
    {
        $this->repository = $repositoryManager->get(Hotel::class);
    }
    
    public function load(HotelId $id): Hotel 
    {
        return $this->repository->load($id->toString());
    }
    
    public function save(Hotel $hotel): void 
    {
        return $this->repository->save($hotel);
    }
    
    public function has(HotelId $id): bool 
    {
        return $this->repository->has($id->toString());
    }
}
```
