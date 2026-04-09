<?php
declare(strict_types=1);

/**
 * Persistencia real de SquadWars.
 */
final class SquadBattleRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByToken(string $token): ?array
    {
        return $this->findByTokenInternal($token, false);
    }

    public function findByTokenForUpdate(string $token): ?array
    {
        return $this->findByTokenInternal($token, true);
    }

    public function findByTokenForUser(string $token, int $userId, bool $forUpdate = false): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        if ($this->hasColumn('user_id')) {
            $stmt = $this->pdo->prepare(
                "SELECT id, battle_token, state_json, user_id
                 FROM knd_squadwars_battles
                 WHERE battle_token = ? AND user_id = ?
                 LIMIT 1{$suffix}"
            );
            $stmt->execute([$token, $userId]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id, battle_token, state_json
                 FROM knd_squadwars_battles
                 WHERE battle_token = ?
                 LIMIT 1{$suffix}"
            );
            $stmt->execute([$token]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !is_array($row)) {
            return null;
        }
        $decoded = json_decode((string) ($row['state_json'] ?? ''), true);
        if (!is_array($decoded)) {
            $decoded = SquadStateV1::emptyShell((string) ($row['battle_token'] ?? ''));
        }
        return [
            'id' => (string) ($row['id'] ?? ''),
            'battle_token' => (string) ($row['battle_token'] ?? ''),
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'state' => $decoded,
        ];
    }

    private function findByTokenInternal(string $token, bool $forUpdate): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->pdo->prepare(
            "SELECT id, battle_token, state_json
             FROM knd_squadwars_battles
             WHERE battle_token = ?
             LIMIT 1{$suffix}"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !is_array($row)) {
            return null;
        }

        $decoded = json_decode((string) ($row['state_json'] ?? ''), true);
        if (!is_array($decoded)) {
            $decoded = SquadStateV1::emptyShell((string) ($row['battle_token'] ?? ''));
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'battle_token' => (string) ($row['battle_token'] ?? ''),
            'state' => $decoded,
        ];
    }

    public function saveState(string $battleId, array $state): void
    {
        $encoded = $this->encodeState($state);
        $stmt = $this->pdo->prepare(
            "UPDATE knd_squadwars_battles
             SET state_json = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$encoded, $battleId]);
        // MySQL puede reportar 0 filas afectadas si state_json no cambió; aún es éxito si la fila existe.
        if ($stmt->rowCount() < 1) {
            $chk = $this->pdo->prepare(
                'SELECT 1 FROM knd_squadwars_battles WHERE id = ? LIMIT 1'
            );
            $chk->execute([$battleId]);
            if (!$chk->fetchColumn()) {
                throw new RuntimeException('battle_not_found');
            }
        }
    }

    /**
     * Crea batalla y retorna token.
     *
     * @param array<string, mixed> $state
     */
    public function createBattle(array $state): string
    {
        $token = (string) ($state['battleId'] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $state['battleId'] = $token;
        }
        $encoded = $this->encodeState($state);

        if ($this->hasColumn('user_id')) {
            $userId = (int) ($state['userId'] ?? 0);
            $stmt = $this->pdo->prepare(
                "INSERT INTO knd_squadwars_battles (battle_token, user_id, state_json)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$token, $userId, $encoded]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO knd_squadwars_battles (battle_token, state_json)
                 VALUES (?, ?)"
            );
            $stmt->execute([$token, $encoded]);
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function encodeState(array $state): string
    {
        $encoded = json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($encoded)) {
            throw new RuntimeException('invalid_payload');
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('invalid_payload');
        }
        return $encoded;
    }

    private function hasColumn(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'knd_squadwars_battles'
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column]);
        $cache[$column] = ((int) $stmt->fetchColumn()) > 0;
        return $cache[$column];
    }
}
