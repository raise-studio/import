<?php

namespace RaiseStudio\Import\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = ['name', 'email', 'phone', 'first_name', 'last_name', 'status'];

    public $timestamps = false;
}
