<?php
/**
 * Test script for Employee CRUD operations
 * Run with: wp eval-file test_employee_crud.php
 */

require_once __DIR__ . '/../../../wp-load.php';

use Pet\UI\Rest\Controller\EmployeeController;
use Pet\Infrastructure\Persistence\Repository\SqlEmployeeRepository;
use Pet\Application\Identity\Command\CreateEmployeeHandler;
use Pet\Application\Identity\Command\UpdateEmployeeHandler;
use Pet\Application\Identity\Command\ArchiveEmployeeHandler;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\Domain\Configuration\Entity\SchemaDefinition;
use Pet\Domain\Configuration\ValueObject\SchemaStatus;

// Mocks
class MockSchemaRepository implements SchemaDefinitionRepository {
    public function save(SchemaDefinition $schema): void {}
    public function findById(int $id): ?SchemaDefinition { return null; }
    public function findActiveByEntityType(string $entityType): ?SchemaDefinition { return null; }
    public function findAll(): array { return []; }
    public function findByStatus(SchemaStatus $status): array { return []; }
}

class MockSchemaValidator extends SchemaValidator {
    public function validateData(array $data, array $schema): array { return []; }
}

global $wpdb;

// 1. Setup Dependencies
$repo = new SqlEmployeeRepository($wpdb);
$schemaRepo = new MockSchemaRepository();
$schemaValidator = new MockSchemaValidator();

$createHandler = new CreateEmployeeHandler($repo, $schemaRepo, $schemaValidator);
$updateHandler = new UpdateEmployeeHandler($repo, $schemaRepo, $schemaValidator);
$archiveHandler = new ArchiveEmployeeHandler($repo);

$controller = new EmployeeController($repo, $createHandler, $updateHandler, $archiveHandler);

// 2. Test Create
echo "Testing Create...\n";
$uniqueSuffix = time();
$wpUserId = 1; // Assuming admin exists
$request = new WP_REST_Request('POST', '/pet/v1/employees');
$request->set_body_params([
    'wpUserId' => $wpUserId,
    'firstName' => 'Test',
    'lastName' => 'User' . $uniqueSuffix,
    'email' => "test{$uniqueSuffix}@example.com",
    'malleableData' => ['mobile' => '1234567890']
]);

$response = $controller->createEmployee($request);
echo "Create Response: " . json_encode($response->get_data()) . "\n";

// Find the created employee
$employee = $repo->findByWpUserId($wpUserId);
if (!$employee) {
    die("Failed to create employee.\n");
}
$id = $employee->id();
echo "Created Employee ID: $id\n";

// 3. Test Update
echo "Testing Update...\n";
$updateRequest = new WP_REST_Request('POST', "/pet/v1/employees/$id"); 
$updateRequest->set_url_params(['id' => $id]);
$updateRequest->set_body_params([
    'wpUserId' => $wpUserId,
    'firstName' => 'Updated',
    'lastName' => 'User' . $uniqueSuffix,
    'email' => "test{$uniqueSuffix}@example.com",
    'malleableData' => ['mobile' => '0987654321']
]);

$updateResponse = $controller->updateEmployee($updateRequest);
echo "Update Response: " . json_encode($updateResponse->get_data()) . "\n";

// Verify Update
$updatedEmployee = $repo->findById($id);
if ($updatedEmployee->firstName() !== 'Updated') {
    echo "ERROR: First name not updated.\n";
} else {
    echo "SUCCESS: First name updated.\n";
}
if ($updatedEmployee->malleableData()['mobile'] !== '0987654321') {
    echo "ERROR: Malleable data not updated.\n";
} else {
    echo "SUCCESS: Malleable data updated.\n";
}

// 4. Test Archive
echo "Testing Archive...\n";
$archiveRequest = new WP_REST_Request('DELETE', "/pet/v1/employees/$id");
$archiveRequest->set_url_params(['id' => $id]);

$archiveResponse = $controller->archiveEmployee($archiveRequest);
echo "Archive Response: " . json_encode($archiveResponse->get_data()) . "\n";

// Verify Archive
$archivedEmployee = $repo->findById($id);
if ($archivedEmployee->archivedAt() === null) {
    echo "ERROR: Employee not archived.\n";
} else {
    echo "SUCCESS: Employee archived at " . $archivedEmployee->archivedAt()->format('Y-m-d H:i:s') . "\n";
}

// Cleanup (Hard delete for test hygiene)
$wpdb->delete($wpdb->prefix . 'pet_employees', ['id' => $id]);
echo "Test data cleaned up.\n";
