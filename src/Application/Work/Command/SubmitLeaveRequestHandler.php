<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\LeaveRequest;
use Pet\Domain\Work\Repository\LeaveRequestRepository;

final class SubmitLeaveRequestHandler
{
    public function __construct(private LeaveRequestRepository $repo)
    {
    }

    public function handle(SubmitLeaveRequestCommand $c): int
    {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16));
        $req = LeaveRequest::draft(
            $uuid,
            $c->employeeId(),
            $c->leaveTypeId(),
            $c->startDate(),
            $c->endDate(),
            $c->notes()
        );
        $req->setStatus('submitted');
        $this->repo->save($req);
        return $req->id();
    }
}

