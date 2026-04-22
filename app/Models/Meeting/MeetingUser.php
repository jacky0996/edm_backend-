<?php

namespace App\Models\Meeting;

use Illuminate\Database\Eloquent\Model;

class MeetingUser extends Model
{
    protected $connection = 'meeting';

    protected $table = 'users';
}
