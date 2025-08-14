<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\UriResolver;

#[AsCommand(
    name: 'app:parse-books',
    description: 'Импорт книг из категории books.toscrape.com'
)]
final class ParseBooksCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('categoryUrl', InputArgument::REQUIRED, 'https://books.toscrape.com/.../index.html')
            ->addOption('min', null, InputOption::VALUE_REQUIRED, 'Минимум карточек для сохранения', 10)
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Задержка между страницами, мс', 600);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $url   = (string)$input->getArgument('categoryUrl');
        $min   = (int)$input->getOption('min');
        $delay = (int)$input->getOption('delay');

        $saved = 0;
        $skipped = 0;
        $reasons = ['duplicate' => 0, 'broken' => 0, 'error' => 0];

        while ($url && $saved < $min) {
            $html = $this->fetchRobust($url, $io);
            $crawler = new Crawler($html, $url);

            foreach ($crawler->filter('.product_pod') as $el) {
                if ($saved >= $min) break;

                $node = new Crawler($el, $url);

                $title    = $node->filter('h3 a')->attr('title') ?? '';
                $priceRaw = $node->filter('.price_color')->count() ? $node->filter('.price_color')->text('') : '';
                $stockTxt = $node->filter('.availability')->count() ? $node->filter('.availability')->text('') : '';
                $href     = $node->filter('h3 a')->attr('href') ?? '';
                $img      = $node->filter('.image_container img')->attr('src') ?? null;

                $productUrl = $this->resolveUrl($url, $href);
                $imageUrl   = $img ? $this->resolveUrl($url, $img) : null;

                // Битые карточки, пропускаем
                if ($title === '' || $priceRaw === '' || $productUrl === '') {
                    $skipped++; $reasons['broken']++;
                    continue;
                }

                // Дедуп без исключений (EM не закроется)
                $exists = $this->em->getRepository(Book::class)->findOneBy(['productUrl' => $productUrl]);
                if ($exists) {
                    $skipped++; $reasons['duplicate']++;
                    continue;
                }

                $price   = $this->normalizePrice($priceRaw);
                $inStock = str_contains(strtolower($stockTxt), 'in stock');

                try {
                    $book = new Book();
                    $book->setTitle($title);
                    $book->setPrice(number_format($price, 2, '.', ''));
                    $book->setInStock($inStock);
                    $book->setProductUrl($productUrl);
                    $book->setImageUrl($imageUrl);


                    $this->em->persist($book);
                    $this->em->flush();
                    $this->em->clear();
                    $saved++;
                } catch (\Throwable $e) {
                    $skipped++; $reasons['error']++;
                    $io->warning('Пропущено: '.$e->getMessage());
                    // после flush-ошибки EM может закрыться — безопасно очистим
                    if (!$this->em->isOpen()) {
                        throw $e;
                    }
                    $this->em->clear();
                }
            }

            // Пагинация
            $next = $crawler->filter('.next a');
            $url = $next->count() ? $this->resolveUrl($url, (string)$next->attr('href')) : null;

            // Вежливость
            usleep($delay * 1000);
        }

        $io->success(sprintf('Сохранено: %d, пропущено: %d (причины: %s)', $saved, $skipped, json_encode($reasons, JSON_UNESCAPED_UNICODE)));
        return Command::SUCCESS;
    }

    private function fetchRobust(string $url, SymfonyStyle $io): string
    {
        $ua = $this->randomUa();
        $backoff = 0.8;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $resp = $this->http->request('GET', $url, [
                    'headers' => ['User-Agent' => $ua, 'Accept' => 'text/html'],
                    'timeout' => 20,
                ]);
                $code = $resp->getStatusCode();
                if ($code === 429 || $code === 403) {
                    $io->note("Подождём ($code), попытка $attempt");
                    usleep((int)($backoff * 1_000_000));
                    $backoff *= 1.7;
                    continue;
                }
                if ($code >= 400) throw new RuntimeException("HTTP $code");
                return $resp->getContent();
            } catch (\Throwable $e) {
                if ($attempt === 5) throw $e;
                $io->note('Повтор из‑за ошибки: '.$e->getMessage());
                usleep((int)($backoff * 1_000_000));
                $backoff *= 1.7;
            }
        }
        throw new RuntimeException('Unreachable');
    }

    private function resolveUrl(string $base, string $rel): string
    {
        if ($rel === '') return '';
        if (str_starts_with($rel, 'http')) return $rel;

        $baseUri = Utils::uriFor($base)->withQuery('')->withFragment('');
        $relUri  = Utils::uriFor($rel);
        return (string) UriResolver::resolve($baseUri, $relUri);
    }

    private function normalizePrice(string $raw): float
    {
        $s = preg_replace('/[^0-9,.\-]/', '', $raw) ?? '';
        if (str_contains($s, ',') && !str_contains($s, '.')) $s = str_replace(',', '.', $s);
        else $s = str_replace(',', '', $s);
        return (float)$s;
    }

    private function randomUa(): string
    {
        $uas = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0',
        ];
        return $uas[array_rand($uas)];
    }
}
