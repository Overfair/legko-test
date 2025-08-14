<?php

namespace App\Dto;

final class UserDto
{
    /**
     * @param OrderDto[] $orders
     */
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly array $orders,
    ) {}
}
