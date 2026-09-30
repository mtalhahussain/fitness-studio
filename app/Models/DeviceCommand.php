<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceCommand extends Model
{
    public const PENDING = 'pending';
    public const SENT    = 'sent';
    public const DONE    = 'done';
    public const FAILED  = 'failed';

    protected $fillable = [
        'device_serial_number', 'user_id', 'command', 'status',
        'cmd_id', 'result', 'sent_at', 'completed_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The id the machine echoes back; the row id is already unique and numeric.
        static::created(function (self $command) {
            if ($command->cmd_id === null) {
                $command->forceFill(['cmd_id' => $command->id])->saveQuietly();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Line the machine expects from GET /iclock/getrequest. */
    public function toAdmsLine(): string
    {
        return "C:{$this->cmd_id}:{$this->command}";
    }
}
