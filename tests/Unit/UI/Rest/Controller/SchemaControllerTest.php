<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Domain\Configuration\Entity\SchemaDefinition;
use Pet\Domain\Configuration\Entity\SchemaStatus;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\UI\Rest\Controller\SchemaController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class SchemaControllerTest extends TestCase
{
    private $schemaRepository;
    private $schemaValidator;
    private $controller;

    protected function setUp(): void
    {
        $this->schemaRepository = $this->createMock(SchemaDefinitionRepository::class);
        $this->schemaValidator = $this->createMock(SchemaValidator::class);
        $this->controller = new SchemaController(
            $this->schemaRepository,
            $this->schemaValidator
        );
    }

    public function testGetSchemasReturnsList(): void
    {
        $schema = new SchemaDefinition(
            'customer',
            1,
            ['fields' => []],
            10,
            SchemaStatus::ACTIVE
        );

        $this->schemaRepository->expects($this->once())
            ->method('findByEntityType')
            ->with('customer')
            ->willReturn([$schema]);

        $request = new WP_REST_Request();
        $request->set_param('entity_type', 'customer');

        $response = $this->controller->getSchemas($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals(10, $data[0]['id']);
        $this->assertEquals('active', $data[0]['status']);
    }

    public function testGetSchemaByIdReturnsSchema(): void
    {
        $schema = new SchemaDefinition(
            'customer',
            1,
            ['fields' => []],
            10,
            SchemaStatus::ACTIVE
        );

        $this->schemaRepository->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willReturn($schema);

        $request = new WP_REST_Request();
        $request->set_param('id', 10);

        $response = $this->controller->getSchemaById($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(10, $data['id']);
        $this->assertEquals('customer', $data['entityType']);
    }

    public function testGetSchemaByIdReturns404IfNotFound(): void
    {
        $this->schemaRepository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $request = new WP_REST_Request();
        $request->set_param('id', 999);

        $response = $this->controller->getSchemaById($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function testCreateDraftSchemaSuccess(): void
    {
        $savedSchema = new SchemaDefinition(
            'customer',
            1,
            ['fields' => []],
            100,
            SchemaStatus::DRAFT
        );

        // Expect findDraftByEntityType to be called twice:
        // 1. Check if draft exists (return null)
        // 2. Fetch the saved schema to return it (return savedSchema)
        $this->schemaRepository->expects($this->exactly(2))
            ->method('findDraftByEntityType')
            ->with('customer')
            ->willReturnOnConsecutiveCalls(null, $savedSchema);

        // Find latest (return null, so version 1)
        $this->schemaRepository->expects($this->once())
            ->method('findLatestByEntityType')
            ->with('customer')
            ->willReturn(null);

        // Save
        $this->schemaRepository->expects($this->once())
            ->method('save');

        $request = new WP_REST_Request();
        $request->set_param('entityType', 'customer');

        $response = $this->controller->createDraftSchema($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(100, $data['id']);
        $this->assertEquals('draft', $data['status']);
    }

    public function testCreateDraftSchemaFailsIfDraftExists(): void
    {
        $existingDraft = new SchemaDefinition('customer', 1, [], 1, SchemaStatus::DRAFT);

        $this->schemaRepository->expects($this->once())
            ->method('findDraftByEntityType')
            ->with('customer')
            ->willReturn($existingDraft);

        $request = new WP_REST_Request();
        $request->set_param('entityType', 'customer');

        $response = $this->controller->createDraftSchema($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('A draft schema already exists for this entity type', $response->get_data()['error']);
    }

    public function testUpdateDraftSchemaSuccess(): void
    {
        $draftSchema = new SchemaDefinition(
            'customer',
            1,
            ['fields' => []],
            10,
            SchemaStatus::DRAFT
        );

        $this->schemaRepository->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willReturn($draftSchema);

        $newFields = [['key' => 'test', 'type' => 'text', 'label' => 'Test', 'required' => false]];
        $fullSchema = ['fields' => $newFields];

        $this->schemaValidator->expects($this->once())
            ->method('validate')
            ->with($fullSchema);

        $this->schemaRepository->expects($this->once())
            ->method('save');

        $request = new WP_REST_Request();
        $request->set_param('id', 10);
        $request->set_json_params(['schema' => $newFields]);

        $response = $this->controller->updateDraftSchema($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals($newFields, $data['schema']);
    }

    public function testUpdateDraftSchemaFailsIfNotDraft(): void
    {
        $activeSchema = new SchemaDefinition(
            'customer',
            1,
            ['fields' => []],
            10,
            SchemaStatus::ACTIVE
        );

        $this->schemaRepository->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willReturn($activeSchema);

        $request = new WP_REST_Request();
        $request->set_param('id', 10);

        $response = $this->controller->updateDraftSchema($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('Only draft schemas can be updated', $response->get_data()['error']);
    }

    public function testPublishSchemaSuccess(): void
    {
        $draftSchema = new SchemaDefinition(
            'customer',
            2,
            ['fields' => []],
            10,
            SchemaStatus::DRAFT
        );

        $this->schemaRepository->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willReturn($draftSchema);

        $this->schemaRepository->expects($this->once())
            ->method('markActiveAsHistorical')
            ->with('customer');

        $this->schemaRepository->expects($this->once())
            ->method('save');

        $request = new WP_REST_Request();
        $request->set_param('id', 10);

        // Mock current user? The controller calls get_current_user_id(). 
        // In a unit test environment, this WP function might need mocking or the test might fail if not defined.
        // The WP_REST_Classes.php stub might handle it or we might need to rely on the environment.
        // Assuming get_current_user_id() is available or stubbed.

        $response = $this->controller->publishSchema($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('active', $data['status']);
    }
}
