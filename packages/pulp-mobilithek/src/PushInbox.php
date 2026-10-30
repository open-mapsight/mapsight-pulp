<?php

declare(strict_types=1);

namespace OpenMapsight\pulpmobilithek;

use PDO;
use RuntimeException;

/** Durable, bounded inbox. Use on local disk outside the document root. */
final class PushInbox
{
    private PDO $db;

    public function __construct(
        private readonly string $path,
        private readonly int $maxPendingBytes = 268435456,
        private readonly int $maxPendingPackets = 10000,
    ) {
        if (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
            throw new RuntimeException('Push inbox directory must exist and be writable');
        }
        $this->db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA busy_timeout = 5000');
        // DELETE avoids persistent WAL files owned by a CLI worker's different uid.
        $this->db->exec('PRAGMA journal_mode = DELETE');
        $this->db->exec('PRAGMA synchronous = EXTRA');
        $this->db->exec('CREATE TABLE IF NOT EXISTS packets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            digest TEXT NOT NULL UNIQUE,
            received_at INTEGER NOT NULL,
            processed_at INTEGER,
            body TEXT,
            metadata TEXT NOT NULL,
            size INTEGER NOT NULL
        )');
        $this->db->exec('CREATE INDEX IF NOT EXISTS packets_pending ON packets(processed_at, id)');
    }

    /** Returns false for an already persisted delivery (including processed ones). */
    public function enqueue(string $body, array $metadata): bool
    {
        $digest = hash('sha256', (string) ($metadata['subscriptionId'] ?? '') . "\0" . $body);
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $find = $this->db->prepare('SELECT id FROM packets WHERE digest = ?');
            $find->execute([$digest]);
            if ($find->fetchColumn() !== false) {
                $this->db->exec('COMMIT');
                return false;
            }
            $pending = $this->stats();
            if ($pending['pendingPackets'] >= $this->maxPendingPackets
                || $pending['pendingBytes'] + strlen($body) > $this->maxPendingBytes) {
                throw new RuntimeException('Push inbox capacity exhausted');
            }
            $insert = $this->db->prepare('INSERT INTO packets(digest, received_at, body, metadata, size) VALUES(?, ?, ?, ?, ?)');
            $insert->execute([$digest, time(), $body, json_encode($metadata, JSON_THROW_ON_ERROR), strlen($body)]);
            $this->db->exec('COMMIT');
            return true;
        } catch (\Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    /**
     * One worker at a time; reception remains independent during processing.
     * Callback must publish successfully before returning. A crash/error keeps
     * the batch pending. Processing must be idempotent (at-least-once delivery).
     *
     * @param callable(list<array<string, mixed>>): void $publish
     */
    public function consume(callable $publish, int $limit = 200): int
    {
        if ($limit < 1 || $limit > 10000) {
            throw new RuntimeException('Invalid push batch limit');
        }
        $lock = fopen($this->path . '.worker.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open push worker lock');
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return 0;
            }
            $rows = $this->db->query('SELECT id, body, metadata FROM packets WHERE processed_at IS NULL ORDER BY id LIMIT ' . $limit)
                ->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                return 0;
            }
            foreach ($rows as &$row) {
                $row['metadata'] = json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR);
            }
            unset($row);
            $publish($rows);
            $this->db->exec('BEGIN IMMEDIATE');
            try {
                $done = $this->db->prepare('UPDATE packets SET processed_at = ?, body = NULL WHERE id = ?');
                foreach ($rows as $row) {
                    $done->execute([time(), $row['id']]);
                }
                // Retain deduplication receipts for seven days, release large bodies immediately.
                $prune = $this->db->prepare('DELETE FROM packets WHERE processed_at < ?');
                $prune->execute([time() - 604800]);
                $this->db->exec('COMMIT');
            } catch (\Throwable $error) {
                $this->db->exec('ROLLBACK');
                throw $error;
            }
            return count($rows);
        } finally {
            fclose($lock);
        }
    }

    /** No payloads, identities or credentials in this report. */
    public function stats(): array
    {
        $row = $this->db->query('SELECT
            COUNT(*) AS pending_packets,
            COALESCE(SUM(size), 0) AS pending_bytes,
            MIN(received_at) AS oldest_pending_at
            FROM packets WHERE processed_at IS NULL')->fetch(PDO::FETCH_ASSOC);
        return [
            'pendingPackets' => (int) $row['pending_packets'],
            'pendingBytes' => (int) $row['pending_bytes'],
            'oldestPendingAt' => $row['oldest_pending_at'] === null ? null : (int) $row['oldest_pending_at'],
            'lastReceivedAt' => $this->nullableTime('SELECT received_at FROM packets ORDER BY id DESC LIMIT 1'),
            'lastProcessedAt' => $this->nullableTime('SELECT MAX(processed_at) FROM packets'),
        ];
    }

    private function nullableTime(string $query): ?int
    {
        $value = $this->db->query($query)->fetchColumn();
        return $value === null || $value === false ? null : (int) $value;
    }
}
