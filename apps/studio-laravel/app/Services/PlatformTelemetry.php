<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PlatformTelemetry
{
    private const REDACTED = '[REDACTED]';
    private const MAX_MESSAGE_LENGTH = 8192;
    private const MAX_STACK_LENGTH = 32768;
    private const MAX_CONTEXT_LENGTH = 32768;

    /**
     * Record telemetry without allowing telemetry failures to affect the request.
     * Secrets are redacted again here even when the caller is trusted.
     */
    public function record(array $event): ?string
    {
        try {
            $occurredAt = now('UTC');
            $retentionDays = max(1, min(365, (int) config('studio.telemetry_retention_days', 30)));
            $context = $this->redact($event['context'] ?? []);
            $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if ($contextJson === false || strlen($contextJson) > self::MAX_CONTEXT_LENGTH) {
                $contextJson = json_encode(['truncated' => true]);
            }

            $id = (string) Str::uuid();
            DB::table('platform_telemetry_events')->insert([
                'id' => $id,
                'occurred_at' => $occurredAt,
                'severity' => $this->bounded($event['severity'] ?? 'error', 16),
                'source' => $this->bounded($event['source'] ?? 'server', 32),
                'event_type' => $this->bounded($event['event_type'] ?? 'error', 64),
                'project_id' => $this->nullableBounded($event['project_id'] ?? null, 128),
                'request_id' => $this->nullableBounded($event['request_id'] ?? null, 128),
                'route' => $this->nullableBounded($event['route'] ?? null, 512),
                'method' => $this->nullableBounded($event['method'] ?? null, 16),
                'status_code' => isset($event['status_code']) ? (int) $event['status_code'] : null,
                'error_class' => $this->nullableBounded($event['error_class'] ?? null, 255),
                'message' => $this->bounded($event['message'] ?? 'Unknown telemetry event', self::MAX_MESSAGE_LENGTH),
                'stack_trace' => $this->nullableBounded($event['stack_trace'] ?? null, self::MAX_STACK_LENGTH),
                'context' => $contextJson,
                'release' => $this->nullableBounded($event['release'] ?? null, 128),
                'user_agent' => $this->nullableBounded($event['user_agent'] ?? null, 1024),
                'expires_at' => $occurredAt->copy()->addDays($retentionDays),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            return $id;
        } catch (Throwable) {
            return null;
        }
    }

    public function recent(array $filters = [], int $limit = 100): array
    {
        $query = DB::table('platform_telemetry_events')
            ->where(function ($builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC'));
            })
            ->orderByDesc('occurred_at')
            ->limit(max(1, min(100, $limit)));

        foreach (['severity', 'source', 'event_type', 'project_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->get()->map(function ($event): array {
            $event = (array) $event;
            $event['context'] = json_decode((string) ($event['context'] ?? '{}'), true) ?: [];
            return $event;
        })->all();
    }

    public function purgeExpired(): int
    {
        return DB::table('platform_telemetry_events')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now('UTC'))
            ->delete();
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $childKey => $childValue) {
                $result[(string) $childKey] = $this->redact($childValue, (string) $childKey);
            }
            return $result;
        }

        if (is_object($value)) {
            return $this->redact((array) $value, $key);
        }

        if (is_string($value)) {
            $value = preg_replace('/\b(Bearer|Basic)\s+[^\s,]+/i', '$1 ' . self::REDACTED, $value) ?? $value;
            return preg_replace('/([?&](?:token|key|secret|password|signature|code)=)[^&\s]+/i', '$1' . self::REDACTED, $value) ?? $value;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:password|passwd|secret|token|api[_-]?key|authorization|cookie|credential|connection[_-]?string|private[_-]?key|access[_-]?key|refresh[_-]?token)/i', $key) === 1;
    }

    private function bounded(mixed $value, int $length): string
    {
        return mb_substr((string) $this->redact($value), 0, $length);
    }

    private function nullableBounded(mixed $value, int $length): ?string
    {
        return $value === null || $value === '' ? null : $this->bounded($value, $length);
    }
}
