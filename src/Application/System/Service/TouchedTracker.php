<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

final class TouchedTracker
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function mark(string $table, int $id, int $employeeId): void
    {
        $cols = (array) $this->wpdb->get_col("DESCRIBE $table", 0);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        if (in_array('metadata_json', $cols, true)) {
            $sql = "UPDATE $table SET metadata_json = JSON_SET(COALESCE(metadata_json, '{}'), '$.touched_at', %s, '$.touched_by_employee_id', %d) WHERE id = %d";
            $this->wpdb->query($this->wpdb->prepare($sql, [$now, $employeeId, $id]));
            return;
        }
        if (in_array('malleable_data', $cols, true)) {
            $sql = "UPDATE $table SET malleable_data = JSON_SET(COALESCE(malleable_data, '{}'), '$.touched_at', %s, '$.touched_by_employee_id', %d) WHERE id = %d";
            $this->wpdb->query($this->wpdb->prepare($sql, [$now, $employeeId, $id]));
            return;
        }
    }
}
