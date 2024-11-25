<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingBundle\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

final class DbalConnectionFactory
{
    /**
     * Mapping was taken from Doctrine Bundle.
     *
     * @see https://github.com/doctrine/DoctrineBundle/blob/7564fa72ab4a87316660347ccd226cefc8fb0ea9/src/ConnectionFactory.php#L35
     */
    private const DEFAULT_SCHEME_MAP = [
        'db2'        => 'ibm_db2',
        'mssql'      => 'pdo_sqlsrv',
        'mysql'      => 'pdo_mysql',
        'mysql2'     => 'pdo_mysql', // Amazon RDS, for some weird reason
        'postgres'   => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql'      => 'pdo_pgsql',
        'sqlite'     => 'pdo_sqlite',
        'sqlite3'    => 'pdo_sqlite',
    ];

    public static function createConnection(string $url): Connection
    {
        return DriverManager::getConnection(
            (new DsnParser(self::DEFAULT_SCHEME_MAP))->parse($url),
        );
    }
}
