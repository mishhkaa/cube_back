<?php

namespace App\Models;

use App\Models\Enums\JobStatus;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DataJob extends Model
{
    use Prunable;

    protected $fillable = ['status', 'action', 'event', 'message', 'payload', 'response', 'queue', 'request_id'];

    protected $casts = [
        'status' => JobStatus::class,
        'payload' => 'array',
        'response' => 'array',
        'request_id' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function prunable(): Builder|DataJob
    {
        return static::query()
            ->where('created_at', '<', Carbon::now()->subWeek());
    }

    public function setProcessing(): static
    {
        $this->update(['status' => JobStatus::PROCESSING]);
        return $this;
    }

    public function setDone(?string $message = null, ?array $response = null): static
    {
        $this->update(array_filter([
            'status' => JobStatus::DONE,
            'message' => $message,
            'response' => $response
        ]));
        return $this;
    }

    public static function getQueryForCountEvents(string $queue)
    {
        return self::query()
            ->select(['event', DB::raw('COUNT(*) AS events_count')])
            ->where('queue', $queue)
            ->groupBy('event');
    }
}
