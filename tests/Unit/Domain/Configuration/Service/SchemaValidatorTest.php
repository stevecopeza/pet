<?php

namespace Pet\Tests\Unit\Domain\Configuration\Service;

use Pet\Domain\Configuration\Service\SchemaValidator;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }

    public function testValidSchema(): void
    {
        $schema = [
            'fields' => [
                [
                    'key' => 'industry',
                    'label' => 'Industry',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['Tech', 'Health']
                ],
                [
                    'key' => 'notes',
                    'label' => 'Notes',
                    'type' => 'textarea',
                    'required' => false
                ]
            ]
        ];

        $this->validator->validate($schema);
        $this->assertTrue(true); // Should not throw exception
    }

    public function testMissingFieldsArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Schema must contain a 'fields' array.");
        $this->validator->validate([]);
    }

    public function testInvalidKeyFormat(): void
    {
        $schema = [
            'fields' => [
                [
                    'key' => 'Invalid Key',
                    'label' => 'Label',
                    'type' => 'text',
                    'required' => false
                ]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field key 'Invalid Key' at index 0 is invalid.");
        $this->validator->validate($schema);
    }

    public function testDuplicateKey(): void
    {
        $schema = [
            'fields' => [
                [
                    'key' => 'field_1',
                    'label' => 'Field 1',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'key' => 'field_1',
                    'label' => 'Field 1 Duplicate',
                    'type' => 'text',
                    'required' => false
                ]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Duplicate field key 'field_1' found at index 1.");
        $this->validator->validate($schema);
    }

    public function testInvalidType(): void
    {
        $schema = [
            'fields' => [
                [
                    'key' => 'field_1',
                    'label' => 'Field 1',
                    'type' => 'unknown_type',
                    'required' => false
                ]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid field type 'unknown_type' at index 0.");
        $this->validator->validate($schema);
    }

    public function testSelectWithoutOptions(): void
    {
        $schema = [
            'fields' => [
                [
                    'key' => 'field_1',
                    'label' => 'Field 1',
                    'type' => 'select',
                    'required' => false
                    // Missing options
                ]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field of type 'select' at index 0 must have a non-empty 'options' array.");
        $this->validator->validate($schema);
    }

    public function testEmptyOptionsForSelect(): void
    {
        $schema = [
            'fields' => [
                [
                    'key' => 'field_1',
                    'label' => 'Field 1',
                    'type' => 'select',
                    'required' => false,
                    'options' => []
                ]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field of type 'select' at index 0 must have a non-empty 'options' array.");
        $this->validator->validate($schema);
    }
}
