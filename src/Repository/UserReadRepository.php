<?php

namespace App\Repository;

use App\Dto\OrderDto;
use App\Dto\UserDto;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class UserReadRepository
{
    public function __construct(private readonly Connection $conn) {}

    public function findWithOrdersByEmail(string $email): ?UserDto
    {
        $sql = <<<SQL
            SELECT
                u.id   AS user_id,
                u.email,
                o.id   AS order_id,
                o.total,
                o.created_at
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            WHERE u.email = :email
            ORDER BY o.id ASC
            SQL;

        $rows = $this->conn->fetchAllAssociative($sql, ['email' => $email]);
        if ($rows === [] ) {
            return null;
        }

        $first = $rows[0];
        $orders = [];
        foreach ($rows as $r) {
            if ($r['order_id'] !== null) {
                $orders[] = new OrderDto(
                    (int)$r['order_id'],
                    (string)$r['total'],
                    new DateTimeImmutable((string)$r['created_at'])
                );
            }
        }

        return new UserDto(
            (int)$first['user_id'],
            (string)$first['email'],
            $orders
        );
    }
}
