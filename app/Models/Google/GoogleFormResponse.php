<?php

namespace App\Models\Google;

use Illuminate\Database\Eloquent\Model;

class GoogleFormResponse extends Model
{
    protected $fillable = [
        'event_id',
        'google_form_id',
        'google_response_id',
        'answers',
        'submitted_at',
        'status',
    ];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'status' => 'integer',
    ];
}
