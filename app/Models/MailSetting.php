<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['enabled', 'host', 'port', 'security', 'username', 'password', 'from_address', 'from_name', 'last_test_at', 'last_test_success', 'last_delivery_at'])]
#[Hidden(['password'])]
class MailSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'port' => 'integer', 'password' => 'encrypted', 'last_test_at' => 'immutable_datetime', 'last_test_success' => 'boolean', 'last_delivery_at' => 'immutable_datetime'];
    }
}
