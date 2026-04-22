<?php

namespace App\Models\EDM;

use App\Models\Google\GoogleForm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $table = 'event';

    protected $guarded = [];

    public function googleForm()
    {
        return $this->hasOne(GoogleForm::class, 'event_id', 'id');
    }
}
