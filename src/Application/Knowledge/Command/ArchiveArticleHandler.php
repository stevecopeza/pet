<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

use Pet\Domain\Knowledge\Repository\ArticleRepository;

class ArchiveArticleHandler
{
    private ArticleRepository $articleRepository;

    public function __construct(ArticleRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }

    public function handle(ArchiveArticleCommand $command): void
    {
        $article = $this->articleRepository->findById($command->id());

        if (!$article) {
            throw new \RuntimeException('Article not found');
        }

        $article->archive();

        $this->articleRepository->save($article);
    }
}
