<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyPayloadMismatchException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PostingIdempotencyService
{
    /**
     * Transient transport fields strictly excluded from payload fingerprint calculation.
     */
    protected const TRANSIENT_FIELDS = [
        'idempotency_key',
        'timestamp',
        'request_time',
        'current_time',
        'request_id',
        '_token',
        'created_at',
        'updated_at',
        'agent_notes_transient',
        'user_id',
        'user_name',
    ];

    /**
     * Known monetary field names normalized using decimal-safe string arithmetic.
     */
    protected const MONETARY_FIELDS = [
        'amount',
        'debit',
        'credit',
        'gross_amount',
        'tax_amount',
        'net_amount',
        'price',
        'total',
        'cost',
    ];

    /**
     * Resolve the canonical business identity 5-tuple and lookup key.
     *
     * @param array $payload
     * @param string $companyCode
     * @param string $domainUuid
     * @return array{
     *     domain_uuid: string,
     *     company_code: string,
     *     source_module: string,
     *     source_transaction_id: string,
     *     operation: string,
     *     version: string,
     *     idempotency_key: string
     * }
     */
    public function resolveCanonicalIdentity(array $payload, string $companyCode, string $domainUuid): array
    {
        $companyCode = strtoupper(trim($companyCode));
        $sourceModule = strtolower(trim((string)($payload['source_module'] ?? 'travelos')));
        $sourceTxId = trim((string)($payload['source_transaction_id'] ?? $payload['reference'] ?? ''));
        $operation = strtolower(trim((string)($payload['operation'] ?? 'post')));
        $version = strtolower(trim((string)($payload['version'] ?? 'v1')));

        $clientKey = trim((string)($payload['idempotency_key'] ?? ''));
        if ($clientKey === '') {
            $clientKey = "{$sourceModule}:{$companyCode}:{$sourceTxId}:{$operation}:{$version}";
        }

        return [
            'domain_uuid' => $domainUuid,
            'company_code' => $companyCode,
            'source_module' => $sourceModule,
            'source_transaction_id' => $sourceTxId,
            'operation' => $operation,
            'version' => $version,
            'idempotency_key' => $clientKey,
        ];
    }

    /**
     * Compute a deterministic SHA-256 fingerprint of the normalized request payload.
     *
     * @param array $payload
     * @param string $companyCode
     * @param array $extraContext Optional resolved accounts or metadata to incorporate
     * @return string 64-character SHA-256 hexadecimal hash
     */
    public function computePayloadHash(array $payload, string $companyCode, array $extraContext = []): string
    {
        $normalized = $this->normalizePayload($payload, $companyCode);

        if (!empty($extraContext)) {
            $normalizedExtra = $this->normalizeArrayRecursive($extraContext);
            $normalized['_resolved_context'] = $normalizedExtra;
            ksort($normalized);
        }

        $canonicalJson = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $canonicalJson);
    }

    /**
     * Normalize a payload structure according to institutional double-entry standards.
     *
     * @param array $payload
     * @param string $companyCode
     * @return array
     */
    protected function normalizePayload(array $payload, string $companyCode): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $keyStr = (string)$key;
            if (in_array(strtolower($keyStr), self::TRANSIENT_FIELDS, true)) {
                continue;
            }

            if (is_array($value)) {
                if ($keyStr === 'entries' || $keyStr === 'lines') {
                    $clean[$keyStr] = $this->normalizeLineItems($value);
                } else {
                    $clean[$keyStr] = $this->normalizeArrayRecursive($value);
                }
                continue;
            }

            if (in_array(strtolower($keyStr), self::MONETARY_FIELDS, true)) {
                $clean[$keyStr] = self::normalizeDecimal($value);
                continue;
            }

            if ($keyStr === 'currency') {
                $clean[$keyStr] = strtoupper(trim((string)$value));
                continue;
            }

            if ($keyStr === 'date' || $keyStr === 'transDate') {
                $clean[$keyStr] = $this->normalizeAccountingDate((string)$value, $companyCode);
                continue;
            }

            if ($keyStr === 'account_code' || $keyStr === 'reference' || $keyStr === 'source_transaction_id') {
                $clean[$keyStr] = trim((string)$value);
                continue;
            }

            $clean[$keyStr] = is_string($value) ? trim($value) : $value;
        }

        ksort($clean);
        return $clean;
    }

    /**
     * Normalize monetary numbers into fixed 4-decimal string representation.
     * Rejects binary floating-point precision loss.
     *
     * @param mixed $val
     * @return string
     */
    public static function normalizeDecimal(mixed $val): string
    {
        if ($val === null || $val === '') {
            return '0.0000';
        }

        $str = trim((string)$val);
        if (stripos($str, 'e') !== false) {
            $str = number_format((float)$str, 8, '.', '');
        }

        $negative = str_starts_with($str, '-');
        $clean = ltrim($str, '+-');
        $parts = explode('.', $clean, 2);
        $intPart = ltrim($parts[0], '0');
        if ($intPart === '') {
            $intPart = '0';
        }

        $fracPart = isset($parts[1]) ? substr($parts[1], 0, 4) : '';
        $fracPart = str_pad($fracPart, 4, '0', STR_PAD_RIGHT);

        if ($intPart === '0' && $fracPart === '0000') {
            return '0.0000';
        }

        return ($negative ? '-' : '') . $intPart . '.' . $fracPart;
    }

    /**
     * Normalize accounting date to YYYY-MM-DD in the company's fiscal timezone.
     *
     * @param string $dateStr
     * @param string $companyCode
     * @return string
     */
    protected function normalizeAccountingDate(string $dateStr, string $companyCode): string
    {
        $trimmed = trim($dateStr);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return $trimmed;
        }

        try {
            $tz = DomainContext::getTimezone($companyCode);
            return Carbon::parse($trimmed)->setTimezone($tz)->format('Y-m-d');
        } catch (\Throwable) {
            return $trimmed;
        }
    }

    /**
     * Normalize and deterministically sort double-entry line items.
     *
     * @param array $lines
     * @return array
     */
    protected function normalizeLineItems(array $lines): array
    {
        $projected = [];
        $index = 0;

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $accountCode = strtoupper(trim((string)($line['account_code'] ?? $line['account'] ?? '')));
            $debit = self::normalizeDecimal($line['debit'] ?? ($line['type'] ?? '' === 'debit' ? ($line['amount'] ?? 0) : 0));
            $credit = self::normalizeDecimal($line['credit'] ?? ($line['type'] ?? '' === 'credit' ? ($line['amount'] ?? 0) : 0));
            $description = trim((string)($line['description'] ?? ''));
            $costCenter = trim((string)($line['cost_center'] ?? ''));
            $taxCode = trim((string)($line['tax_code'] ?? ''));

            $projected[] = [
                'account_code' => $accountCode,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $description,
                'cost_center' => $costCenter,
                'tax_code' => $taxCode,
                'line_index' => $index++,
            ];
        }

        // Composite deterministic sort: account_code ASC, debit ASC, credit ASC, description ASC, line_index ASC
        usort($projected, function (array $a, array $b) {
            $cmp = strcmp($a['account_code'], $b['account_code']);
            if ($cmp !== 0) return $cmp;

            $cmp = strcmp($a['debit'], $b['debit']);
            if ($cmp !== 0) return $cmp;

            $cmp = strcmp($a['credit'], $b['credit']);
            if ($cmp !== 0) return $cmp;

            $cmp = strcmp($a['description'], $b['description']);
            if ($cmp !== 0) return $cmp;

            return $a['line_index'] <=> $b['line_index'];
        });

        return $projected;
    }

    /**
     * Recursively normalize and sort associative arrays.
     *
     * @param array $arr
     * @return array
     */
    protected function normalizeArrayRecursive(array $arr): array
    {
        $result = [];
        foreach ($arr as $k => $v) {
            $keyStr = (string)$k;
            if (in_array(strtolower($keyStr), self::TRANSIENT_FIELDS, true)) {
                continue;
            }

            if (is_array($v)) {
                $result[$keyStr] = $this->normalizeArrayRecursive($v);
            } elseif (in_array(strtolower($keyStr), self::MONETARY_FIELDS, true)) {
                $result[$keyStr] = self::normalizeDecimal($v);
            } elseif (is_string($v)) {
                $result[$keyStr] = trim($v);
            } else {
                $result[$keyStr] = $v;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Find an existing idempotency record by client lookup key or canonical 5-tuple.
     *
     * @param string $domainUuid
     * @param string $idempotencyKey
     * @param array $canonicalIdentity
     * @return PostingIdempotencyRecord|null
     */
    public function findExistingRecord(
        string $domainUuid,
        string $idempotencyKey,
        array $canonicalIdentity
    ): ?PostingIdempotencyRecord {
        // 1. Primary lookup by tenant-scoped client key
        $record = PostingIdempotencyRecord::forDomain($domainUuid)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        // 2. Authoritative lookup by canonical 5-tuple (prevents divergent client keys)
        if (!$record && !empty($canonicalIdentity['source_transaction_id'])) {
            $record = PostingIdempotencyRecord::forDomain($domainUuid)
                ->where('source_module', $canonicalIdentity['source_module'])
                ->where('source_transaction_id', $canonicalIdentity['source_transaction_id'])
                ->where('operation', $canonicalIdentity['operation'])
                ->where('version', $canonicalIdentity['version'])
                ->first();
        }

        return $record;
    }

    /**
     * Evaluate an existing idempotency record against the current payload fingerprint.
     *
     * @param PostingIdempotencyRecord $record
     * @param string $currentHash
     * @return array{
     *     action: 'replay'|'conflict'|'in_flight'|'retry_failed',
     *     record: PostingIdempotencyRecord,
     *     response?: array
     * }
     */
    public function evaluateReplay(PostingIdempotencyRecord $record, string $currentHash): array
    {
        if ($record->status === 'committed') {
            if ($record->payload_hash === $currentHash) {
                return [
                    'action' => 'replay',
                    'record' => $record,
                    'response' => $record->response_data ?? [
                        'status' => 'posted',
                        'is_idempotent_replay' => true,
                        'voucher_reference' => $record->voucher_reference,
                        'journal_entry_id' => $record->journal_entry_id,
                        'message' => 'Expense voucher already committed (Idempotent replay).',
                    ],
                ];
            }

            return [
                'action' => 'conflict',
                'record' => $record,
            ];
        }

        if ($record->status === 'failed') {
            return [
                'action' => 'retry_failed',
                'record' => $record,
            ];
        }

        return [
            'action' => 'in_flight',
            'record' => $record,
        ];
    }

    /**
     * Pre-reserve a new in-flight idempotency record.
     *
     * @param array $canonicalIdentity
     * @param string $payloadHash
     * @param array $requestPayload
     * @return PostingIdempotencyRecord
     */
    public function reserveInFlight(
        array $canonicalIdentity,
        string $payloadHash,
        array $requestPayload
    ): PostingIdempotencyRecord {
        return PostingIdempotencyRecord::create([
            'domain_uuid' => $canonicalIdentity['domain_uuid'],
            'company_code' => $canonicalIdentity['company_code'],
            'idempotency_key' => $canonicalIdentity['idempotency_key'],
            'source_module' => $canonicalIdentity['source_module'],
            'source_transaction_id' => $canonicalIdentity['source_transaction_id'],
            'operation' => $canonicalIdentity['operation'],
            'version' => $canonicalIdentity['version'],
            'payload_hash' => $payloadHash,
            'request_payload' => $requestPayload,
            'status' => 'in_flight',
            'lease_token' => (string) Str::uuid(),
            'lease_generation' => 1,
            'lease_expires_at' => Carbon::now()->addSeconds(60),
        ]);
    }

    /**
     * Determine whether a QueryException represents a unique constraint violation.
     * Strictly supports the repository's target engines: PostgreSQL (23505) and SQLite (19 / 2067).
     *
     * @param QueryException $e
     * @return bool
     */
    public static function isUniqueConstraintViolation(QueryException $e): bool
    {
        $code = (string)$e->getCode();
        $message = strtolower($e->getMessage());

        // PostgreSQL: SQLSTATE 23505
        if ($code === '23505' || str_contains($message, '23505') || str_contains($message, 'unique constraint')) {
            return true;
        }

        // SQLite: error code 19 (or 2067 SQLITE_CONSTRAINT_UNIQUE)
        if ($code === '19' || $code === '2067' || str_contains($message, 'unique constraint failed')) {
            return true;
        }

        return false;
    }

    /**
     * Concurrency-safe reservation acquisition and unique constraint collision recovery.
     *
     * @param array $canonicalIdentity
     * @param string $payloadHash
     * @param array $requestPayload
     * @param int $maxWaitMs Maximum milliseconds to poll/wait for an in-flight competing worker
     * @return array{
     *     status: 'acquired'|'replay'|'conflict'|'in_progress',
     *     record?: PostingIdempotencyRecord,
     *     response?: array
     * }
     */
    public function acquireReservation(
        array $canonicalIdentity,
        string $payloadHash,
        array $requestPayload,
        int $maxWaitMs = 500
    ): array {
        $domainUuid = $canonicalIdentity['domain_uuid'];
        $idempotencyKey = $canonicalIdentity['idempotency_key'];

        // 1. Initial lookup
        $record = $this->findExistingRecord($domainUuid, $idempotencyKey, $canonicalIdentity);

        if ($record) {
            return $this->resolveExistingReservation($record, $canonicalIdentity, $payloadHash, $requestPayload, $maxWaitMs);
        }

        // 2. Pre-reserve via INSERT
        try {
            $record = $this->reserveInFlight($canonicalIdentity, $payloadHash, $requestPayload);
            return [
                'status' => 'acquired',
                'record' => $record,
            ];
        } catch (QueryException $e) {
            // Check if this is truly a unique constraint violation
            if (!self::isUniqueConstraintViolation($e)) {
                // DO NOT SWALLOW UNRELATED DATABASE ERRORS
                throw $e;
            }

            // We lost the concurrent insert race!
            $winnerRecord = $this->findExistingRecord($domainUuid, $idempotencyKey, $canonicalIdentity);

            if (!$winnerRecord) {
                // In the rare event the winner rolled back and vanished, attempt insert once more
                $record = $this->reserveInFlight($canonicalIdentity, $payloadHash, $requestPayload);
                return [
                    'status' => 'acquired',
                    'record' => $record,
                ];
            }

            return $this->resolveExistingReservation($winnerRecord, $canonicalIdentity, $payloadHash, $requestPayload, $maxWaitMs);
        }
    }

    /**
     * Resolve an existing reservation record against the current request.
     *
     * @param PostingIdempotencyRecord $record
     * @param array $canonicalIdentity
     * @param string $payloadHash
     * @param array $requestPayload
     * @param int $maxWaitMs
     * @return array{
     *     status: 'acquired'|'replay'|'conflict'|'in_progress',
     *     record?: PostingIdempotencyRecord,
     *     response?: array
     * }
     */
    protected function resolveExistingReservation(
        PostingIdempotencyRecord $record,
        array $canonicalIdentity,
        string $payloadHash,
        array $requestPayload,
        int $maxWaitMs
    ): array {
        // Case A: Record already committed
        if ($record->isCommitted()) {
            if ($record->payload_hash === $payloadHash) {
                return [
                    'status' => 'replay',
                    'record' => $record,
                    'response' => $record->response_data ?? [
                        'status' => 'posted',
                        'is_idempotent_replay' => true,
                        'voucher_reference' => $record->voucher_reference,
                        'journal_entry_id' => $record->journal_entry_id,
                        'message' => 'Expense voucher already committed (Idempotent replay).',
                    ],
                ];
            }

            return [
                'status' => 'conflict',
                'record' => $record,
            ];
        }

        // Case B: Record failed - can be retried / re-reserved
        if ($record->isFailed()) {
            $updated = PostingIdempotencyRecord::whereKey($record->id)
                ->where('status', 'failed')
                ->update([
                    'status' => 'in_flight',
                    'payload_hash' => $payloadHash,
                    'request_payload' => $requestPayload,
                    'lease_token' => (string) Str::uuid(),
                    'lease_generation' => $record->lease_generation + 1,
                    'lease_expires_at' => Carbon::now()->addSeconds(60),
                    'error_message' => null,
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                $record->refresh();
                return [
                    'status' => 'acquired',
                    'record' => $record,
                ];
            }
        }

        // Case C: Record is currently in-flight
        if ($record->isInFlight()) {
            // If the payload hash differs, this is an immediate conflict under the same identity
            if ($record->payload_hash !== $payloadHash) {
                return [
                    'status' => 'conflict',
                    'record' => $record,
                ];
            }

            // If lease has expired, attempt stale lease takeover
            if ($record->isLeaseExpired()) {
                $newToken = (string) Str::uuid();
                $updated = PostingIdempotencyRecord::whereKey($record->id)
                    ->where('status', 'in_flight')
                    ->where('lease_token', $record->lease_token)
                    ->where('lease_expires_at', '<', Carbon::now())
                    ->update([
                        'lease_token' => $newToken,
                        'lease_generation' => $record->lease_generation + 1,
                        'lease_expires_at' => Carbon::now()->addSeconds(60),
                        'payload_hash' => $payloadHash,
                        'request_payload' => $requestPayload,
                        'updated_at' => now(),
                    ]);

                if ($updated > 0) {
                    $record->refresh();
                    return [
                        'status' => 'acquired',
                        'record' => $record,
                    ];
                }
            }

            // Polling loop for active in-flight worker
            if ($maxWaitMs > 0) {
                $start = microtime(true);
                while ((microtime(true) - $start) * 1000 < $maxWaitMs) {
                    usleep(25000); // 25ms
                    $record->refresh();

                    if ($record->isCommitted()) {
                        if ($record->payload_hash === $payloadHash) {
                            return [
                                'status' => 'replay',
                                'record' => $record,
                                'response' => $record->response_data ?? [],
                            ];
                        }
                        return [
                            'status' => 'conflict',
                            'record' => $record,
                        ];
                    }

                    if ($record->isFailed()) {
                        break;
                    }
                }
            }

            return [
                'status' => 'in_progress',
                'record' => $record,
            ];
        }

        return [
            'status' => 'in_progress',
            'record' => $record,
        ];
    }
}
