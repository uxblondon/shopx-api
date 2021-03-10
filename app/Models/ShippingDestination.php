<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingDestination extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string 
     */
    protected $table = 'shipping_destinations';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
