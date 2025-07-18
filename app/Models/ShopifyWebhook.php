<?php

// app/Models/ShopifyWebhook.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyWebhook extends Model
{
    protected $fillable = ['payload', 'response'];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];
}
 //

