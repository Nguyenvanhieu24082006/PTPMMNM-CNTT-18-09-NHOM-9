<?php

declare(strict_types=1);
require_once __DIR__ . '/Database.php';
final class RegistrationRepository
{
    public function __construct(private mysqli $connection) {}
    public function save(int $eventId, string $name, string $email): string
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        if (
            $eventId <= 0 || $name === '' || strlen($name) > 120
            || str_contains($name, "\0") || strlen($email) > 200
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidArgumentException('REGISTRATION_INVALID');
        }
        try {
            $statement = queryParams(
                $this->connection,
                'INSERT INTO registrations (event_id,name,email) VALUES (?,?,?)',
                [$eventId, $name, $email]
            );
            $statement->close();
            return 'CREATED';
        } catch (RuntimeException $e) {
            // Only duplicate-key errors are duplicates; FK/check/write errors propagate.
            if ($e->getCode() === 1062) {
                return 'DUPLICATE';
            }
            throw $e;
        }
    }
    public function listForEvent(int $eventId): array
    {
        $result = queryParams(
            $this->connection,
            'SELECT id,event_id,name,email FROM registrations WHERE event_id=? ORDER BY id',
            [$eventId]
        );
        try {
            return $result->get_result()->fetch_all(MYSQLI_ASSOC);
        } finally {
            $result->close();
        }
    }
}
