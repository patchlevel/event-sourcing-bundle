<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Aggregate
{
    private string $name;
    private ?string $snapshotStore;

    public function __construct(string $name, ?string $snapshotStore = null)
    {
        $this->name = $name;
        $this->snapshotStore = $snapshotStore;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSnapshotStore(): ?string
    {
        return $this->snapshotStore;
    }
}
