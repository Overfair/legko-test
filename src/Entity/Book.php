<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'books')]
#[ORM\Index(name: 'idx_books_price', columns: ['price'])]
#[ORM\Index(name: 'idx_books_in_stock', columns: ['in_stock'])]
class Book
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(type: 'boolean')]
    private bool $inStock;

    #[ORM\Column(name: 'product_url', type: 'string', length: 512, unique: true)]
    private string $productUrl;

    #[ORM\Column(name: 'image_url', type: 'string', length: 512, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getPrice(): string { return $this->price; }
    public function setPrice(string $price): void { $this->price = $price; }
    public function isInStock(): bool { return $this->inStock; }
    public function setInStock(bool $inStock): void { $this->inStock = $inStock; }
    public function getProductUrl(): string { return $this->productUrl; }
    public function setProductUrl(string $url): void { $this->productUrl = $url; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $url): void { $this->imageUrl = $url; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
