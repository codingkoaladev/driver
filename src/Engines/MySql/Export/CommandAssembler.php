<?php

declare(strict_types=1);

namespace Driver\Engines\MySql\Export;

use Driver\Engines\ConnectionInterface;
use Driver\Pipeline\Environment\EnvironmentInterface;

use function array_diff;
use function array_map;
use function escapeshellarg;
use function array_unshift;
use function implode;
use function in_array;

class CommandAssembler
{
    private TablesProvider $tablesProvider;

    public function __construct(TablesProvider $tablesProvider)
    {
        $this->tablesProvider = $tablesProvider;
    }

    /**
     * @return string[]
     */
    public function execute(
        ConnectionInterface $connection,
        EnvironmentInterface $environment,
        string $dumpFile,
        string $triggersDumpFile
    ): array {
        $allTables = $this->tablesProvider->getAllTables($connection);
        $ignoredTables = $this->tablesProvider->getIgnoredTables($environment);
        if (array_diff($allTables, $ignoredTables) === []) {
            return [];
        }
        $emptyTables = $this->tablesProvider->getEmptyTables($environment);
        $commands = [$this->getStructureCommand($connection, $ignoredTables, $dumpFile)];
        foreach ($allTables as $table) {
            if (in_array($table, $ignoredTables) || in_array($table, $emptyTables)) {
                continue;
            }
            $commands[] = $this->getDataCommand($connection, [$table], $dumpFile);
        }
        array_unshift(
            $commands,
            "echo '/*!40014 SET @ORG_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;'"
            . ' | gzip >> ' . escapeshellarg($dumpFile)
        );
        $commands[] = "echo '/*!40014 SET FOREIGN_KEY_CHECKS=@ORG_FOREIGN_KEY_CHECKS */;'"
            . ' | gzip >> ' . escapeshellarg($dumpFile);
        $commands[] = $this->getTriggersCommand($connection, $ignoredTables, $triggersDumpFile);
        return $commands;
    }

    /**
     * @param string[] $ignoredTables
     */
    private function getStructureCommand(
        ConnectionInterface $connection,
        array $ignoredTables,
        string $dumpFile
    ): string {
        $parts = [
            'mysqldump --user=' . escapeshellarg($connection->getUser()),
            '--password=' . escapeshellarg($connection->getPassword()),
            "--single-transaction",
            "--no-tablespaces",
            "--no-data",
            "--skip-triggers",
            '--host=' . escapeshellarg($connection->getHost()),
            escapeshellarg($connection->getDatabase())
        ];
        foreach ($ignoredTables as $table) {
            $parts[] = '--ignore-table=' . escapeshellarg($connection->getDatabase() . '.' . $table);
        }
        $parts[] = "| sed -E 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g'";
        $parts[] = "| gzip";
        $parts[] = '>> ' . escapeshellarg($dumpFile);
        return implode(' ', $parts);
    }

    /**
     * @param string[] $tables
     */
    private function getDataCommand(ConnectionInterface $connection, array $tables, string $dumpFile): string
    {
        $parts = [
            'mysqldump --user=' . escapeshellarg($connection->getUser()),
            '--password=' . escapeshellarg($connection->getPassword()),
            "--single-transaction",
            "--no-tablespaces",
            "--no-create-info",
            "--skip-triggers",
            '--host=' . escapeshellarg($connection->getHost()),
            escapeshellarg($connection->getDatabase()),
            implode(' ', array_map('escapeshellarg', $tables))
        ];
        $parts[] = "| gzip";
        $parts[] = '>> ' . escapeshellarg($dumpFile);
        return implode(' ', $parts);
    }

    /**
     * @param string[] $ignoredTables
     */
    private function getTriggersCommand(ConnectionInterface $connection, array $ignoredTables, string $dumpFile): string
    {
        $parts = [
            'mysqldump --user=' . escapeshellarg($connection->getUser()),
            '--password=' . escapeshellarg($connection->getPassword()),
            "--single-transaction",
            "--no-tablespaces",
            "--no-data",
            "--no-create-info",
            "--triggers",
            '--host=' . escapeshellarg($connection->getHost()),
            escapeshellarg($connection->getDatabase())
        ];
        foreach ($ignoredTables as $table) {
            $parts[] = '--ignore-table=' . escapeshellarg($connection->getDatabase() . '.' . $table);
        }
        $parts[] = "| sed -E 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g'";
        $parts[] = "| gzip";
        $parts[] = '>> ' . escapeshellarg($dumpFile);
        return implode(' ', $parts);
    }
}
