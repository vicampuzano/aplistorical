<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationJobs extends Model
{
    protected $casts = [
        'source_config' => 'array',
        'destination_config' => 'array'
    ];

}
