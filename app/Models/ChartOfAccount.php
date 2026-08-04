<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'account_code',
        'account_name',
        'type'
    ];

    public function generalLedgers()
    {
        return $this->hasMany(GeneralLedger::class, 'account_id');
    }
    public function cashFlows()
    {
        return $this->hasMany(CashFlow::class, 'account_id');
    }
}
