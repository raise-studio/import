<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\LowercasePipe;
use RaiseStudio\Import\Tests\TestCase;

class LowercasePipeTest extends TestCase
{
    /** @test */
    public function it_converts_email_to_lowercase()
    {
        $pipe = new LowercasePipe(['email']);

        $result = $pipe->handle(
            ['email' => 'ALICE@EXAMPLE.COM'],
            fn ($row) => $row,
        );

        $this->assertEquals('alice@example.com', $result['email']);
    }

    /** @test */
    public function it_converts_multiple_specified_fields()
    {
        $pipe = new LowercasePipe(['email', 'username']);

        $result = $pipe->handle(
            ['email' => 'ALICE@TEST.COM', 'username' => 'AliceUser'],
            fn ($row) => $row,
        );

        $this->assertEquals('alice@test.com', $result['email']);
        $this->assertEquals('aliceuser', $result['username']);
    }

    /** @test */
    public function it_ignores_unspecified_fields()
    {
        $pipe = new LowercasePipe(['email']);

        $result = $pipe->handle(
            ['email' => 'ALICE@TEST.COM', 'name' => 'Alice'],
            fn ($row) => $row,
        );

        $this->assertEquals('Alice', $result['name']);
    }

    /** @test */
    public function it_uses_default_fields()
    {
        $pipe = new LowercasePipe();

        $result = $pipe->handle(
            ['email' => 'ALICE@TEST.COM', 'username' => 'AliceUser', 'name' => 'Alice'],
            fn ($row) => $row,
        );

        $this->assertEquals('alice@test.com', $result['email']);
        $this->assertEquals('aliceuser', $result['username']);
        $this->assertEquals('Alice', $result['name']);
    }

    /** @test */
    public function it_handles_missing_field_gracefully()
    {
        $pipe = new LowercasePipe(['email']);

        $result = $pipe->handle(
            ['name' => 'Alice'],
            fn ($row) => $row,
        );

        $this->assertEquals('Alice', $result['name']);
    }

    /** @test */
    public function it_handles_non_string_field()
    {
        $pipe = new LowercasePipe(['count']);

        $result = $pipe->handle(
            ['count' => 123],
            fn ($row) => $row,
        );

        $this->assertSame(123, $result['count']);
    }

    /** @test */
    public function it_handles_unicode_lowercase()
    {
        $pipe = new LowercasePipe(['email']);

        $result = $pipe->handle(
            ['email' => 'İNFO@EXAMPLE.COM'],
            fn ($row) => $row,
        );

        // mb_strtolower handles Turkish İ correctly
        $this->assertEquals('i̇nfo@example.com', $result['email']);
    }
}
