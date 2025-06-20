<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Article;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;

class EntityPreloadBlogManyHasOneTest extends TestCase
{

    public function testManyHasOneUnoptimized(): void
    {
        $this->createDummyBlogData(5, 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 5, 'query' => 'SELECT * FROM category t0 WHERE t0.id = ?'],
        ]);
    }

    public function testManyHasOneWithManualPreload(): void
    {
        $this->createDummyBlogData(5, 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();

        $categoryIds = array_map(
            static fn (Article $article) => $article->getCategory() ? $article->getCategory()->getId() : null,
            $articles,
        );
        $categoryIds = array_filter($categoryIds, static fn (?int $id) => $id !== null);

        if (count($categoryIds) > 0) {
            $this->getEntityManager()->createQueryBuilder()
                ->select('category')
                ->from(Category::class, 'category')
                ->where('category.id IN (:ids)')
                ->setParameter('ids', array_values(array_unique($categoryIds)))
                ->getQuery()
                ->getResult();
        }

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    public function testManyHasOneWithFetchJoin(): void
    {
        $this->createDummyBlogData(5, 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article', 'category')
            ->from(Article::class, 'article')
            ->leftJoin('article.category', 'category')
            ->getQuery()
            ->getResult();

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_ LEFT JOIN category c1_ ON a0_.category_id = c1_.id'],
        ]);
    }

    public function testManyHasOneWithEagerFetchMode(): void
    {
        $this->createDummyBlogData(5, 5);

        $articles = $this->getEntityManager()->createQueryBuilder()
            ->select('article')
            ->from(Article::class, 'article')
            ->getQuery()
            ->setFetchMode(Article::class, 'category', ClassMetadata::FETCH_EAGER)
            ->getResult();

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article a0_'],
            ['count' => 1, 'query' => 'SELECT * FROM category t0 WHERE t0.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    public function testManyHasOneWithPreload(): void
    {
        $this->createDummyBlogData(5, 5);

        $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();
        $this->getEntityPreloader()->preload($articles, 'category');

        $this->readArticleCategoryNames($articles);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM article t0'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.id IN (?, ?, ?, ?, ?)'],
        ]);
    }

    /**
     * @param array<Article> $articles
     */
    private function readArticleCategoryNames(array $articles): void
    {
        foreach ($articles as $article) {
            if ($article->getCategory()) {
                $article->getCategory()->getName();
            }
        }
    }

}
