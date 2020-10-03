<?php

namespace Illuminate\Tests\Console\Scheduling;

use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\CommandBuilder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CommandBuilderTest extends TestCase
{
    /**
     * @var \Illuminate\Console\Scheduling\CacheEventMutex
     */
    protected $cacheMutex;

    /**
     * @var \Illuminate\Contracts\Cache\Factory
     */
    protected $cacheFactory;

    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cacheRepository;

    public function setUp(): void
    {
        $this->cacheFactory = m::mock(Factory::class);
        $this->cacheRepository = m::mock(Repository::class);
        $this->cacheMutex = new CacheEventMutex($this->cacheFactory);
    }

    public function testBackgroundCommandBuiltEscapesUser(): void
    {
        if (windows_os() === true) {
            self::markTestSkipped('Test only applicable for Unix');
        }

        $commandBuilder = new CommandBuilder();
        $event = new Event($this->cacheMutex, 'my:command');
        $event->runInBackground();
        // Example of malicious intent
        //$userInput = '$USER -- cat /etc/passwd && sudo -u $USER';
        $event->user('username');
        // Username should be escaped with quote marks
        $expectedEscapedCommand = "sudo -u 'username' -- sh -c '('my:command' > '/dev/null' 2>&1; '/usr/bin/php7.4' artisan schedule:finish 'framework/schedule-b0f275b1612ced9f3f3d098bfd67a6f33081d64e' $?) > '/dev/null' 2>&1 &'";

        $builtCommand = $commandBuilder->buildCommand($event);

        self::assertSame($expectedEscapedCommand, $builtCommand);
    }

    public function testCommandBuiltEscapesUser(): void
    {
        if (windows_os() === true) {
            self::markTestSkipped('Test only applicable for Unix');
        }

        $commandBuilder = new CommandBuilder();
        //$userInput = '$USER -- cat /etc/passwd && sudo -u $USER';
        $userInput = 'my:command';
        $event = new Event($this->cacheMutex, $userInput);
        // Example of malicious intent
        $event->user('username');
        // Username should be escaped with quote marks
        $expectedEscapedCommand = "sudo -u 'username' -- sh -c ''my:command' > '/dev/null' 2>&1'";

        $builtCommand = $commandBuilder->buildCommand($event);

        self::assertSame($expectedEscapedCommand, $builtCommand);
    }

    public function testBackgroundCommandBuilt(): void
    {
        if (windows_os() === true) {
            self::markTestSkipped('Test only applicable for Unix');
        }

        $commandBuilder = new CommandBuilder();
        //$userInput = '$USER -- cat /etc/passwd && sudo -u $USER';
        $userInput = 'my:command';
        $event = new Event($this->cacheMutex, $userInput);
        $event->runInBackground();
        // Example of malicious intent
        $expectedEscapedCommand = "('my:command' > '/dev/null' 2>&1; '/usr/bin/php7.4' artisan schedule:finish 'framework/schedule-b0f275b1612ced9f3f3d098bfd67a6f33081d64e' $?) > '/dev/null' 2>&1 &";

        $builtCommand = $commandBuilder->buildCommand($event);

        self::assertSame($expectedEscapedCommand, $builtCommand);
    }

    public function testCommandBuilt(): void
    {
        if (windows_os() === true) {
            self::markTestSkipped('Test only applicable for Unix');
        }

        $commandBuilder = new CommandBuilder();
        //$userInput = '$USER -- cat /etc/passwd && sudo -u $USER';
        $userInput = 'my:command';
        $event = new Event($this->cacheMutex, $userInput);
        // Example of malicious intent
        $expectedEscapedCommand = "'my:command' > '/dev/null' 2>&1";

        $builtCommand = $commandBuilder->buildCommand($event);
        
        self::assertSame($expectedEscapedCommand, $builtCommand);
    }
}
