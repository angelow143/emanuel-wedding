<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RSVP extends Model
{
    protected $table = 'rsvps';
    protected $fillable = ['name', 'email', 'attending', 'message'];
}
