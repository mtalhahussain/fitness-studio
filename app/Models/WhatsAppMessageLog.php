<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessageLog extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'user_id',
        'invoice_id',
        'payment_id',
        'phone',
        'template_name',
        'message_body',
        'response',
        'status',
        'reminder_date',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'reminder_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
