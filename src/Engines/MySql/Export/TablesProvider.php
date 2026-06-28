<?php

declare(strict_types=1);

namespace Driver\Engines\MySql\Export;

use Driver\Engines\ConnectionInterface;
use Driver\Pipeline\Environment\EnvironmentInterface;
use PDO;
use RuntimeException;

use function array_map;

class TablesProvider
{
    /**
     * @return string[]
     */
    public function getAllTables(ConnectionInterface $connection): array
    {
        $statement = $connection->getConnection()->prepare(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = :database ORDER BY table_name'
        );
        $statement->execute(['database' => $connection->getDatabase()]);

        $result = $statement->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($result) || $result === []) {
            throw new RuntimeException('Unable to get table names');
        }

        return array_map('strval', $result);
    }

    /**
     * @return string[]
     */
    public function getEmptyTables(EnvironmentInterface $environment): array
    {
        return $environment->getEmptyTables();
    }

    /**
     * @return string[]
     */
    public function getIgnoredTables(EnvironmentInterface $environment): array
    {
        return $environment->getIgnoredTables();
    }
}
