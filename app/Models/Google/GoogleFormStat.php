<?php

namespace App\Models\Google;

use Illuminate\Database\Eloquent\Model;

class GoogleFormStat extends Model
{
    protected $fillable = [
        'event_id',
        'google_form_id',
        'view_count',
        'response_count',
    ];
}
