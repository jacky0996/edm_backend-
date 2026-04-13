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

    public function stat()
    {
        return $this->hasOne(GoogleFormStat::class, 'google_form_id', 'id');
    }

    public function responses()
    {
        return $this->hasMany(GoogleFormResponse::class, 'google_form_id', 'id');
    }
}
