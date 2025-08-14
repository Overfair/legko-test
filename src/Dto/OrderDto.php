<?php

namespace App\Dto;

use DateTimeImmutable;

final class OrderDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $total,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}
