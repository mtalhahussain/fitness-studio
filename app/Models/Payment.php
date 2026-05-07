<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasGymScope;

    protected $fillable = [
        'invoice_id', 'gym_id', 'amount', 'method', 'reference_number', 'notes', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
