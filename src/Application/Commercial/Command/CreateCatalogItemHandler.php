<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Entity\CatalogItem;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;

class CreateCatalogItemHandler
{
    private CatalogItemRepository $repository;

    public function __construct(CatalogItemRepository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(CreateCatalogItemCommand $command): void
    {
        $item = new CatalogItem(
            $command->name(),
            $command->unitPrice(),
            $command->unitCost(),
            $command->type(),
            $command->sku(),
            $command->description(),
            $command->category(),
            $command->wbsTemplate()
        );

        $this->repository->save($item);
    }
}
