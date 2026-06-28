<?php

declare(strict_types=1);

namespace Driver\Tests\Unit\System;

use Driver\System\Configuration;
use Driver\System\DebugExternalConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\ConsoleOutput;

class DebugExternalConnectionTest extends TestCase
{
    /** @var Configuration&MockObject */
    private MockObject $configurationMock;

    private DebugExternalConnection $connection;

    public function setUp(): void
    {
        $this->configurationMock = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->connection = new DebugExternalConnection($this->configurationMock, new ConsoleOutput());
    }

    public function testGetsUserFromGeneratedUserKey(): void
    {
        $this->configurationMock->method('getNode')
            ->willReturnMap([
                ['connections/mysql_debug/user', 'dev_user']
            ]);

        $this->assertSame('dev_user', $this->connection->getUser());
    }

    public function testGetsUserFromLegacyUsernameKey(): void
    {
        $this->configurationMock->method('getNode')
            ->willReturnMap([
                ['connections/mysql_debug/user', null],
                ['connections/mysql_debug/username', 'legacy_user']
            ]);

        $this->assertSame('legacy_user', $this->connection->getUser());
    }
}
