<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

use Pet\Domain\Knowledge\Entity\Article;
use Pet\Domain\Knowledge\Repository\ArticleRepository;

class CreateArticleHandler
{
    private ArticleRepository $articleRepository;

    public function __construct(ArticleRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }

    public function handle(CreateArticleCommand $command): void
    {
        $article = new Article(
            $command->title(),
            $command->content(),
            $command->category(),
            $command->status()
        );

        $this->articleRepository->save($article);
    }
}
