<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/books')]
final class BooksController extends AbstractController
{
    #[Route('', name: 'books_index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/books',
        summary: 'Список книг с фильтрами и пагинацией.',
        parameters: [
            new OA\Parameter(
                name: 'max_price', in: 'query',
                description: 'Максимальная цена',
                schema: new OA\Schema(type: 'number', example: 20.00)
            ),
            new OA\Parameter(
                name: 'in_stock', in: 'query',
                description: 'Только в наличии (1/0)',
                schema: new OA\Schema(type: 'integer', enum: [0, 1])
            ),
            new OA\Parameter(
                name: 'limit', in: 'query',
                description: 'Размер страницы (1..100)',
                schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)
            ),
            new OA\Parameter(
                name: 'page', in: 'query',
                description: 'Страница (>=1)',
                schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: Book::class))
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'page',  type: 'integer'),
                                new OA\Property(property: 'limit', type: 'integer'),
                                new OA\Property(property: 'count', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $maxPrice = $req->query->get('max_price');
        $inStock  = $req->query->get('in_stock');
        $limit    = (int)$req->query->get('limit', 20);
        $page     = (int)$req->query->get('page', 1);

        if ($limit < 1 || $limit > 100) return $this->json(['error' => 'limit must be between 1 and 100'], 422);
        if ($page < 1) return $this->json(['error' => 'page must be >= 1'], 422);
        if ($maxPrice !== null && !is_numeric($maxPrice)) return $this->json(['error' => 'max_price must be numeric'], 422);
        if ($inStock !== null && !in_array((string)$inStock, ['0','1'], true)) return $this->json(['error' => 'in_stock must be 0 or 1'], 422);

        $qb = $em->getRepository(Book::class)->createQueryBuilder('b');
        if ($maxPrice !== null) $qb->andWhere('b.price <= :max')->setParameter('max', (string)$maxPrice);
        if ($inStock !== null)  $qb->andWhere('b.inStock = :st')->setParameter('st', (bool)$inStock);

        $total = (int)(clone $qb)->select('COUNT(b.id)')->getQuery()->getSingleScalarResult();
        $limit = min(max($limit, 1), 100);

        $qb->orderBy('b.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);


        $items = $qb->getQuery()->getResult();

        $data = array_map(fn(Book $b) => [
            'id'          => $b->getId(),
            'title'       => $b->getTitle(),
            'price'       => $b->getPrice(),
            'in_stock'    => $b->isInStock(),
            'product_url' => $b->getProductUrl(),
            'image_url'   => $b->getImageUrl(),
        ], $items);

        return $this->json([
            'data' => $data,
            'meta' => ['page' => $page, 'limit' => $limit, 'count' => count($data), 'total' => $total],
        ]);
    }

    #[Route('/{id}', name: 'books_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/books/{id}',
        summary: 'Детальная карточка',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: new Model(type: Book::class))),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $id, EntityManagerInterface $em): JsonResponse
    {
        $book = $em->getRepository(Book::class)->find($id);
        if (!$book) return $this->json(['error' => 'Not found'], 404);

        return $this->json([
            'id'          => $book->getId(),
            'title'       => $book->getTitle(),
            'price'       => $book->getPrice(),
            'in_stock'    => $book->isInStock(),
            'product_url' => $book->getProductUrl(),
            'image_url'   => $book->getImageUrl(),
        ]);
    }
}
