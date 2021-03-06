<?php

namespace Phpactor\Tests\Unit\Extension\Core\Console\Dumper;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\Core\Console\Dumper\DumperRegistry;
use Phpactor\Extension\Core\Console\Dumper\Dumper;
use Prophecy\PhpUnit\ProphecyTrait;

class DumperRegistryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @testdox It throws exception if dumper not found.
     */
    public function testNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown dumper "foobar", known dumpers: "dumper1"');
        $registry = $this->create([
            'dumper1' => $this->prophesize(Dumper::class)->reveal(),
        ]);

        $registry->get('foobar');
    }

    /**
     * @testdox It returns the requested dumper.
     */
    public function testGetDumper()
    {
        $registry = $this->create([
            'foobar' => $dumper = $this->prophesize(Dumper::class)->reveal(),
        ]);

        $this->assertSame($dumper, $registry->get('foobar'));
    }

    /**
     * @testdox It should use default if no argument given.
     */
    public function testDefault()
    {
        $registry = $this->create([
            'foobar' => $dumper = $this->prophesize(Dumper::class)->reveal(),
        ], 'foobar');

        $this->assertSame($dumper, $registry->get());
    }

    private function create(array $dumpers, $default = 'default')
    {
        return new DumperRegistry($dumpers, $default);
    }
}
