<?php

namespace App\Tests\Repository;

use App\Dto\UserDto;
use App\Repository\UserReadRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

final class UserReadRepositoryTest extends KernelTestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->conn = self::getContainer()->get('doctrine')->getConnection();

        $this->dropIfExists('orders');
        $this->dropIfExists('users');

        $schema = new Schema();

        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true]);
        $users->addColumn('email', 'string', ['length' => 190]);
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email']);

        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true]);
        $orders->addColumn('user_id', 'integer');
        $orders->addColumn('total', 'decimal', ['precision' => 10, 'scale' => 2]);
        $orders->addColumn('created_at', 'datetime');
        $orders->setPrimaryKey(['id']);
        $orders->addIndex(['user_id']);

        foreach ($schema->toSql($this->conn->getDatabasePlatform()) as $sql) {
            $this->conn->executeStatement($sql);
        }

        $this->conn->insert('users', ['email' => 'adilet@mail.kz']);
        $userId = (int) $this->conn->lastInsertId();

        $this->conn->insert('orders', [
            'user_id' => $userId,
            'total' => '9.99',
            'created_at' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
        ]);
        $this->conn->insert('orders', [
            'user_id' => $userId,
            'total' => '25.55',
            'created_at' => (new DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s'),
        ]);
    }

    public function testNotFoundUser(): void
    {
        $repo = new UserReadRepository($this->conn);
        $dto = $repo->findWithOrdersByEmail('molya@mail.kz');
        self::assertNull($dto);
    }

    public function testFoundWithTwoOrders(): void
    {
        $repo = new UserReadRepository($this->conn);
        $dto = $repo->findWithOrdersByEmail('adilet@mail.kz');
        self::assertInstanceOf(UserDto::class, $dto);
        self::assertSame('adilet@mail.kz', $dto->email);
        self::assertCount(2, $dto->orders);
    }

    public function testSqlInjectionAttemptIsSafe(): void
    {
        $repo = new UserReadRepository($this->conn);
        $payload = "adilet@mail.kz' OR 1=1 --";
        $dto = $repo->findWithOrdersByEmail($payload);
        self::assertNull($dto);
    }

    public function testNoNPlusOne_ExactlyOneQuery(): void
    {
        $counting = new class($this->conn) extends Connection {
            public int $calls = 0;
            private Connection $inner;
            public function __construct(Connection $inner) {
                $this->inner = $inner;
                parent::__construct(
                    $inner->getParams(),
                    $inner->getDriver(),
                    $inner->getConfiguration(),
                    $inner->getEventManager()
                );
            }
            public function fetchAllAssociative(string $sql, array $params = [], array $types = []): array {
                $this->calls++;
                return $this->inner->fetchAllAssociative($sql, $params, $types);
            }
        };

        $repo = new UserReadRepository($counting);
        $dto = $repo->findWithOrdersByEmail('adilet@mail.kz');

        self::assertInstanceOf(UserDto::class, $dto);
        self::assertSame(1, $counting->calls);
    }

    private function dropIfExists(string $table): void
    {
        try { $this->conn->executeStatement("DROP TABLE IF EXISTS {$table}"); } catch (Throwable) {}
    }
}
