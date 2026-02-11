<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

use Pet\Domain\Knowledge\Repository\ArticleRepository;

class UpdateArticleHandler
{
    private ArticleRepository $articleRepository;

    public function __construct(ArticleRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }

    public function handle(UpdateArticleCommand $command): void
    {
        $article = $this->articleRepository->findById($command->id());

        if (!$article) {
            throw new \RuntimeException('Article not found');
        }

        $article->update(
            $command->title(),
            $command->content(),
            $command->category(),
            $command->status(),
            $command->malleableData()
        );

        $this->articleRepository->save($article);
    }
}
