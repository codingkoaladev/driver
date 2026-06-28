<?php

declare(strict_types=1);

namespace Driver\Tests\Unit\Engines\MySql\Export;

use Driver\Engines\ConnectionInterface;
use Driver\Engines\MySql\Export\CommandAssembler;
use Driver\Engines\MySql\Export\TablesProvider;
use Driver\Pipeline\Environment\EnvironmentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CommandAssemblerTest extends TestCase
{
    private CommandAssembler $commandAssembler;

    /** @var TablesProvider&MockObject */
    private MockObject $tablesProviderMock;

    /** @var ConnectionInterface&MockObject */
    private MockObject $connectionMock;

    /** @var EnvironmentInterface&MockObject */
    private MockObject $environmentMock;

    public function setUp(): void
    {
        $this->tablesProviderMock = $this->getMockBuilder(TablesProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->connectionMock = $this->getMockBuilder(ConnectionInterface::class)->getMockForAbstractClass();
        $this->connectionMock->expects($this->any())->method('getUser')->willReturn('user');
        $this->connectionMock->expects($this->any())->method('getPassword')->willReturn('password');
        $this->connectionMock->expects($this->any())->method('getHost')->willReturn('host');
        $this->connectionMock->expects($this->any())->method('getDatabase')->willReturn('db');
        $this->environmentMock = $this->getMockBuilder(EnvironmentInterface::class)->getMockForAbstractClass();
        $this->commandAssembler = new CommandAssembler($this->tablesProviderMock);
    }

    public function testReturnsEmptyArrayIfNoTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn([]);
        $this->assertSame(
            [],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.gz', 'triggers.gz')
        );
    }

    public function testReturnsEmptyArrayIfAllTablesAreIgnored(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn(['a', 'b']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn(['a', 'b']);
        $this->assertSame(
            [],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.gz', 'triggers.gz')
        );
    }

    public function testReturnsCommandsForNormalTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn(['a', 'b']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn([]);
        $this->assertSame(
            [
                $this->disableForeignKeyChecksCommand(),
                $this->schemaCommand(),
                $this->dataCommand("'a'"),
                $this->dataCommand("'b'"),
                $this->restoreForeignKeyChecksCommand(),
                $this->triggersCommand()
            ],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.gz', 'triggers.gz')
        );
    }

    public function testReturnsCommandsForEmptyTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn(['a', 'b']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn([]);
        $this->tablesProviderMock->expects($this->any())->method('getEmptyTables')->willReturn(['a', 'b']);
        $this->assertSame(
            [
                $this->disableForeignKeyChecksCommand(),
                $this->schemaCommand(),
                $this->restoreForeignKeyChecksCommand(),
                $this->triggersCommand()
            ],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.gz', 'triggers.gz')
        );
    }

    public function testReturnsCommandsForMixedTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')
            ->willReturn(['a', 'b', 'c', 'd', 'e', 'f']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn(['c', 'f']);
        $this->tablesProviderMock->expects($this->any())->method('getEmptyTables')->willReturn(['b', 'e']);
        $this->assertSame(
            [
                $this->disableForeignKeyChecksCommand(),
                $this->schemaCommand("--ignore-table='db.c' --ignore-table='db.f'"),
                $this->dataCommand("'a'"),
                $this->dataCommand("'d'"),
                $this->restoreForeignKeyChecksCommand(),
                $this->triggersCommand("--ignore-table='db.c' --ignore-table='db.f'")
            ],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.gz', 'triggers.gz')
        );
    }

    private function disableForeignKeyChecksCommand(): string
    {
        return "echo '/*!40014 SET @ORG_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;'"
            . " | gzip >> 'dump.gz'";
    }

    private function restoreForeignKeyChecksCommand(): string
    {
        return "echo '/*!40014 SET FOREIGN_KEY_CHECKS=@ORG_FOREIGN_KEY_CHECKS */;' | gzip >> 'dump.gz'";
    }

    private function schemaCommand(string $ignoredTables = ''): string
    {
        return $this->baseDumpCommand('--no-data --skip-triggers', $ignoredTables)
            . ' ' . $this->definerReplacementCommand()
            . " | gzip >> 'dump.gz'";
    }

    private function dataCommand(string $tables): string
    {
        return $this->baseDumpCommand('--no-create-info --skip-triggers', $tables)
            . " | gzip >> 'dump.gz'";
    }

    private function triggersCommand(string $ignoredTables = ''): string
    {
        return $this->baseDumpCommand('--no-data --no-create-info --triggers', $ignoredTables)
            . ' ' . $this->definerReplacementCommand()
            . " | gzip >> 'triggers.gz'";
    }

    private function baseDumpCommand(string $options, string $suffix): string
    {
        return "mysqldump --user='user' --password='password' --single-transaction --no-tablespaces "
            . "{$options} --host='host' 'db'"
            . ($suffix ? " {$suffix}" : '');
    }

    private function definerReplacementCommand(): string
    {
        return "| sed -E 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g'";
    }
}
