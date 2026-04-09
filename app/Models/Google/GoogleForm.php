<?php

namespace App\Models\Google;
use App\Models\EDM\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleForm extends Model
{
    use SoftDeletes;

    protected $table = 'google_form';
    protected $fillable = [
        'event_id',
        'form_id',
        'form_url',
        'type',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }
}
