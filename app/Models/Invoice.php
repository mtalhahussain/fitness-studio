<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'gym_id', 'user_id', 'invoice_number', 'subtotal', 'tax_amount',
        'discount_amount', 'total_amount', 'status', 'notes', 'due_date', 'paid_at',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'due_date'        => 'date',
        'paid_at'         => 'datetime',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function items()    { return $this->hasMany(InvoiceItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePaid($query)    { return $query->where('status', 'paid'); }
    public function scopeUnpaid($query)  { return $query->whereIn('status', ['unpaid', 'partially_paid']); }

    public function isPaid(): bool      { return $this->status === 'paid'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function amountPaid(): float
    {
        // uses withSum result if eager-loaded, avoids N+1
        return (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount'));
    }

    public function amountDue(): float
    {
        return max(0, (float) $this->total_amount - $this->amountPaid());
    }
}
