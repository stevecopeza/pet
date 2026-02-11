<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Identity;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Identity\Entity\Contact;
use Pet\Infrastructure\Persistence\Repository\SqlContactRepository;

class SqlContactRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlContactRepository($this->wpdb);
    }

    public function testSaveInsert()
    {
        $contact = new Contact('John', 'Doe', 'john@example.com');

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_contacts',
                $this->callback(function ($data) {
                    return $data['email'] === 'john@example.com';
                }),
                $this->anything()
            );

        $this->repository->save($contact);
    }

    public function testFindById()
    {
        $row = (object) [
            'id' => '456',
            'customer_id' => '1',
            'site_id' => null,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => null,
            'malleable_schema_version' => null,
            'malleable_data' => null,
            'created_at' => '2023-01-01 00:00:00',
            'archived_at' => null
        ];

        $this->wpdb->method('get_row')->willReturn($row);
        $this->wpdb->method('get_results')->willReturn([]);

        $contact = $this->repository->findById(456);

        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals(456, $contact->id());
        $this->assertEquals('john@example.com', $contact->email());
    }
}
