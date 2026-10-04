<?php

namespace App\Models;

use Database\Factories\BudgetIncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'expected_cents', 'received_cents', 'received_date', 'expected_date'])]
class BudgetIncome extends Model
{
    /** @use HasFactory<BudgetIncomeFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expected_cents' => 'integer', 'received_cents' => 'integer', 'received_date' => 'immutable_date', 'expected_date' => 'immutable_date'];
    }
}
