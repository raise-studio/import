<?php

namespace RaiseStudio\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RaiseStudio\Import\Mappers\AutoMapper;
use RaiseStudio\Import\Fields\Field;

class AutoMapperTest extends TestCase
{
    /** @test */
    public function it_maps_exact_matches()
    {
        $mapper = new AutoMapper();
        $fields = [
            Field::make('name')->label('Name'),
            Field::make('email')->label('Email'),
        ];

        $mapping = $mapper->map(['Name', 'Email'], $fields);

        $this->assertEquals('name', $mapping['Name']);
        $this->assertEquals('email', $mapping['Email']);
    }

    /** @test */
    public function it_maps_fuzzy_matches()
    {
        $mapper = new AutoMapper();
        $fields = [
            Field::make('name')->label('Full Name'),
            Field::make('email')->label('Email Address'),
        ];

        $mapping = $mapper->map(['Full Name', 'Email Address'], $fields);

        $this->assertEquals('name', $mapping['Full Name']);
        $this->assertEquals('email', $mapping['Email Address']);
    }

    /** @test */
    public function it_handles_unmatched_headers()
    {
        $mapper = new AutoMapper();
        $fields = [
            Field::make('name')->label('Name'),
        ];

        $mapping = $mapper->map(['Name', 'Unknown Column'], $fields);

        $this->assertEquals('name', $mapping['Name']);
        $this->assertEquals('', $mapping['Unknown Column']);
    }
}
