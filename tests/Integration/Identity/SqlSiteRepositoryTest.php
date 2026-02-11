<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Identity;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Identity\Entity\Site;
use Pet\Infrastructure\Persistence\Repository\SqlSiteRepository;

class SqlSiteRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlSiteRepository($this->wpdb);
    }

    public function testSaveInsert()
    {
        $site = new Site(1, 'Test Site');

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_sites',
                $this->callback(function ($data) {
                    return $data['customer_id'] === 1 && $data['name'] === 'Test Site';
                }),
                $this->anything()
            );

        $this->repository->save($site);
    }

    public function testSaveUpdate()
    {
        $site = new Site(1, 'Test Site', null, null, null, null, null, 'active', 123);

        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_sites',
                $this->anything(),
                ['id' => 123],
                $this->anything(),
                ['%d']
            );

        $this->repository->save($site);
    }

    public function testFindById()
    {
        $row = (object) [
            'id' => '123',
            'customer_id' => '1',
            'name' => 'Test Site',
            'address_lines' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
            'malleable_schema_version' => null,
            'malleable_data' => null,
            'created_at' => '2023-01-01 00:00:00',
            'archived_at' => null
        ];

        $this->wpdb->method('get_row')->willReturn($row);

        $site = $this->repository->findById(123);

        $this->assertInstanceOf(Site::class, $site);
        $this->assertEquals(123, $site->id());
        $this->assertEquals('Test Site', $site->name());
    }
}
