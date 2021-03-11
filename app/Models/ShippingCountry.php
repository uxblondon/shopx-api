<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ShippingCountry extends Model
{
     /**
     * The table associated with the model.
     *
     * @var string 
     */
    protected $table = 'shipping_countries';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
