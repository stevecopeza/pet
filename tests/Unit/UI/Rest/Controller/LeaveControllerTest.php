<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

function current_user_can($capability) { return true; }

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Domain\Work\Entity\LeaveType;
use Pet\Domain\Work\Entity\LeaveRequest;
use Pet\Domain\Work\Repository\LeaveTypeRepository;
use Pet\Domain\Work\Repository\LeaveRequestRepository;
use Pet\Application\Work\Command\SubmitLeaveRequestHandler;
use Pet\Application\Work\Command\DecideLeaveRequestHandler;
use Pet\Application\Work\Command\SetCapacityOverrideHandler;
use Pet\UI\Rest\Controller\LeaveController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class LeaveControllerTest extends TestCase
{
    private $types;
    private $requests;
    private $submitHandler;
    private $decideHandler;
    private $overrideHandler;
    private $controller;

    protected function setUp(): void
    {
        $this->types = $this->createMock(LeaveTypeRepository::class);
        $this->requests = $this->createMock(LeaveRequestRepository::class);
        $this->submitHandler = new \Pet\Application\Work\Command\SubmitLeaveRequestHandler($this->requests);
        $this->decideHandler = new \Pet\Application\Work\Command\DecideLeaveRequestHandler($this->requests);
        $capacityRepo = $this->createMock(\Pet\Domain\Work\Repository\CapacityOverrideRepository::class);
        $this->overrideHandler = new \Pet\Application\Work\Command\SetCapacityOverrideHandler($capacityRepo);

        $this->controller = new LeaveController(
            $this->types,
            $this->requests,
            $this->submitHandler,
            $this->decideHandler,
            $this->overrideHandler
        );
    }

    public function testListTypesReturnsArray(): void
    {
        $this->types->method('findAll')->willReturn([
            new LeaveType(1, 'Annual', true),
            new LeaveType(2, 'Unpaid', false),
        ]);
        $res = $this->controller->listTypes(new WP_REST_Request());
        $this->assertEquals(200, $res->get_status());
        $data = $res->get_data();
        $this->assertCount(2, $data);
        $this->assertEquals('Annual', $data[0]['name']);
    }

    public function testListMyRequestsReturnsArray(): void
    {
        $req = LeaveRequest::draft('uuid', 10, 1, new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-01-02'), null);
        $req->setId(5);
        $this->requests->method('findByEmployee')->willReturn([$req]);
        $r = new WP_REST_Request();
        $r->set_param('employeeId', 10);
        $res = $this->controller->listMyRequests($r);
        $this->assertEquals(200, $res->get_status());
        $data = $res->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals(5, $data[0]['id']);
    }

    public function testSubmitInvokesHandlerAndReturnsId(): void
    {
        $this->types->method('findById')->willReturn(new LeaveType(2, 'Annual', true));
        $this->requests->method('save')->willReturnCallback(function ($req) {
            $req->setId(42);
        });
        $r = new WP_REST_Request();
        $r->set_param('employeeId', 10);
        $r->set_param('leaveTypeId', 2);
        $r->set_param('startDate', '2025-01-01');
        $r->set_param('endDate', '2025-01-02');
        $r->set_param('notes', 'n');
        $res = $this->controller->submit($r);
        $this->assertEquals(201, $res->get_status());
        $this->assertEquals(['id' => 42], $res->get_data());
    }

    public function testDecideInvokesHandler(): void
    {
        $req = LeaveRequest::draft('uuid', 10, 1, new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-01-02'), null);
        $req->setId(5);
        $this->requests->method('findById')->willReturn($req);
        $r = new WP_REST_Request();
        $r->set_param('id', 5);
        $r->set_param('decidedByEmployeeId', 9);
        $r->set_param('decision', 'approved');
        $r->set_param('reason', null);
        $res = $this->controller->decide($r);
        $this->assertEquals(200, $res->get_status());
        $this->assertEquals(['status' => 'approved'], $res->get_data());
    }

    public function testSetOverrideInvokesHandler(): void
    {
        $r = new WP_REST_Request();
        $r->set_param('employeeId', 10);
        $r->set_param('date', '2025-01-01');
        $r->set_param('capacityPct', 80);
        $r->set_param('reason', 'note');
        $res = $this->controller->setOverride($r);
        $this->assertEquals(200, $res->get_status());
        $this->assertEquals(['status' => 'ok'], $res->get_data());
    }
}
