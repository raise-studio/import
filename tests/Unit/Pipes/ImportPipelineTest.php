<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\ImportPipeline;
use RaiseStudio\Import\Pipes\TrimStringsPipe;
use RaiseStudio\Import\Pipes\LowercasePipe;
use RaiseStudio\Import\Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    /** @test */
    public function it_runs_pipes_in_order()
    {
        $pipeline = new ImportPipeline();

        $called = [];
        $pipeline->pipe(new class($called) implements \RaiseStudio\Import\Pipes\ImportPipe {
            public function __construct(private array &$called) {}

            public function handle(array $row, \Closure $next): array
            {
                $this->called[] = 'first';
                return $next($row);
            }
        });
        $pipeline->pipe(new class($called) implements \RaiseStudio\Import\Pipes\ImportPipe {
            public function __construct(private array &$called) {}

            public function handle(array $row, \Closure $next): array
            {
                $this->called[] = 'second';
                return $next($row);
            }
        });

        $pipeline->send(['name' => 'test']);

        $this->assertEquals(['first', 'second'], $called);
    }

    /** @test */
    public function it_skips_global_pipes_when_none_registered()
    {
        $pipeline = new ImportPipeline();

        $result = $pipeline->send(['name' => 'test']);

        $this->assertEquals(['name' => 'test'], $result);
    }

    /** @test */
    public function field_pipe_only_affects_target_field()
    {
        $pipeline = new ImportPipeline();
        $pipeline->fieldPipe('email', new LowercasePipe(['email']));

        $result = $pipeline->send([
            'name' => 'Alice',
            'email' => 'ALICE@EXAMPLE.COM',
        ]);

        $this->assertEquals('Alice', $result['name']);
        $this->assertEquals('alice@example.com', $result['email']);
    }

    /** @test */
    public function global_pipes_run_before_field_pipes()
    {
        $order = [];

        $pipeline = new ImportPipeline();
        $pipeline->pipe(new class($order) implements \RaiseStudio\Import\Pipes\ImportPipe {
            public function __construct(private array &$order) {}
            public function handle(array $row, \Closure $next): array
            {
                $this->order[] = 'global';
                return $next($row);
            }
        });
        $pipeline->fieldPipe('name', new class($order) implements \RaiseStudio\Import\Pipes\ImportPipe {
            public function __construct(private array &$order) {}
            public function handle(array $row, \Closure $next): array
            {
                $this->order[] = 'field';
                return $next($row);
            }
        });

        $pipeline->send(['name' => 'test']);

        $this->assertEquals(['global', 'field'], $order);
    }

    /** @test */
    public function field_pipe_is_skipped_when_field_missing()
    {
        $called = false;

        $pipeline = new ImportPipeline();
        $pipeline->fieldPipe('missing', new class($called) implements \RaiseStudio\Import\Pipes\ImportPipe {
            public function __construct(private bool &$called) {}
            public function handle(array $row, \Closure $next): array
            {
                $this->called = true;
                return $next($row);
            }
        });

        $pipeline->send(['name' => 'test']);

        $this->assertFalse($called);
    }

    /** @test */
    public function pipe_accepts_class_string()
    {
        $pipeline = new ImportPipeline();
        $pipeline->pipe(TrimStringsPipe::class);

        $result = $pipeline->send(['name' => '  hello  ']);

        $this->assertEquals(['name' => 'hello'], $result);
    }

    /** @test */
    public function isEmpty_returns_true_when_no_pipes()
    {
        $pipeline = new ImportPipeline();

        $this->assertTrue($pipeline->isEmpty());
    }

    /** @test */
    public function isEmpty_returns_false_when_pipes_registered()
    {
        $pipeline = new ImportPipeline();
        $pipeline->pipe(TrimStringsPipe::class);

        $this->assertFalse($pipeline->isEmpty());
    }

    /** @test */
    public function count_returns_number_of_global_pipes()
    {
        $pipeline = new ImportPipeline();
        $pipeline->pipe(TrimStringsPipe::class);
        $pipeline->pipe(new LowercasePipe());

        $this->assertEquals(2, $pipeline->count());
    }

    /** @test */
    public function pipeline_is_serializable()
    {
        $pipeline = new ImportPipeline();
        $pipeline->pipe(TrimStringsPipe::class);
        $pipeline->pipe(new LowercasePipe(['email']));
        $pipeline->fieldPipe('password', new \RaiseStudio\Import\Pipes\BcryptPipe('password'));

        $serialized = serialize($pipeline);
        $restored = unserialize($serialized);

        $this->assertInstanceOf(ImportPipeline::class, $restored);
        $this->assertEquals(2, $restored->count());
    }

    /** @test */
    public function pipe_works_after_serialize_deserialize()
    {
        $pipeline = new ImportPipeline();
        $pipeline->pipe(TrimStringsPipe::class);
        $pipeline->pipe(new LowercasePipe(['email']));

        $serialized = serialize($pipeline);
        $restored = unserialize($serialized);

        $result = $restored->send([
            'name' => '  Alice  ',
            'email' => 'ALICE@EXAMPLE.COM',
        ]);

        $this->assertEquals('Alice', $result['name']);
        $this->assertEquals('alice@example.com', $result['email']);
    }
}
