# Normalizer

Sometimes you also want to add more complex data in events as payload or in aggregates for the snapshots. 
For example DateTime, enums or value objects. You can do that too. 
However, you must define a normalizer for this so that the library knows how to write this data to the database 
and load it again.

!!! info

    You can find out more about normalizer in the library 
    [documentation](https://event-sourcing.patchlevel.io/latest/normalizer/). 
    This documentation is limited to bundle integration.

## Built-in Normalizer

This bundle adds more Symfony specific normalizers in addition to the existing built-in normalizers.

!!! note

    You can find the other build-in normalizers [here](https://event-sourcing.patchlevel.io/latest/normalizer/#built-in-normalizer)

### Uuid

With the `Uuid` Normalizer, as the name suggests, you can convert Symfony Uuid objects to a string and back again.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcingBundle\Normalizer\UuidNormalizer;
use Symfony\Component\Uid\Uuid;

final class DTO 
{
    #[Normalize(new UuidNormalizer())]
    public Uuid $id;
}
```