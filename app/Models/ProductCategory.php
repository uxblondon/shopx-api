<?php

namespace App\Models;


class ProductCategory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string 
     */
    protected $table = 'product_categories';

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
