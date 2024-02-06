<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

final class DbalConnectionFactory
{
    public static function createConnection(string $url): Connection
    {
        return DriverManager::getConnection(
            (new DsnParser())->parse($url),
        );
    }
}
