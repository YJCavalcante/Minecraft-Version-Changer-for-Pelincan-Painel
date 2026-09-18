<?php

namespace Pelican\Versions\Models;

use App\Models\Server;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionChange extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_CHANGING = 'changing';
    public const STATUS_DONE     = 'done';
    public const STATUS_FAILED   = 'failed';

    protected $table = 'version_changes';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'server_id'     => 'integer',
            'build_number'  => 'integer',
            'jar_size'      => 'integer',
            'clean_install' => 'boolean',
            'created_at'    => 'immutable_datetime',
            'updated_at'    => 'immutable_datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function appendLog(string $line): void
    {
        $timestamp = now()->format('H:i:s');
        $current   = $this->log ?? '';
        $this->update(['log' => $current . "[{$timestamp}] {$line}\n"]);
    }

    public function markChanging(): void
    {
        $this->update(['status' => self::STATUS_CHANGING]);
    }

    public function markDone(): void
    {
        $this->update(['status' => self::STATUS_DONE]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CHANGING], true);
    }
}
