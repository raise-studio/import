<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\BcryptPipe;
use RaiseStudio\Import\Tests\TestCase;

class BcryptPipeTest extends TestCase
{
    /** @test */
    public function it_hashes_password_with_bcrypt()
    {
        $pipe = new BcryptPipe('password');

        $result = $pipe->handle(
            ['password' => 'secret123'],
            fn ($row) => $row,
        );

        $this->assertNotEquals('secret123', $result['password']);
        $this->assertStringStartsWith('$2y$', $result['password']);
        $this->assertTrue(password_verify('secret123', $result['password']));
    }

    /** @test */
    public function it_uses_default_password_field()
    {
        $pipe = new BcryptPipe();

        $result = $pipe->handle(
            ['password' => 'mypassword'],
            fn ($row) => $row,
        );

        $this->assertStringStartsWith('$2y$', $result['password']);
    }

    /** @test */
    public function it_handles_missing_field_gracefully()
    {
        $pipe = new BcryptPipe('password');

        $result = $pipe->handle(
            ['name' => 'Alice'],
            fn ($row) => $row,
        );

        $this->assertEquals(['name' => 'Alice'], $result);
    }

    /** @test */
    public function it_ignores_non_string_password()
    {
        $pipe = new BcryptPipe('password');

        $result = $pipe->handle(
            ['password' => null],
            fn ($row) => $row,
        );

        $this->assertNull($result['password']);
    }

    /** @test */
    public function it_hashes_custom_field()
    {
        $pipe = new BcryptPipe('secret');

        $result = $pipe->handle(
            ['secret' => 'mysecret'],
            fn ($row) => $row,
        );

        $this->assertTrue(password_verify('mysecret', $result['secret']));
    }
}
