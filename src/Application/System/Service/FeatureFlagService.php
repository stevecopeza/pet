<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

use Pet\Domain\Configuration\Repository\SettingRepository;

class FeatureFlagService
{
    private SettingRepository $settings;

    public function __construct(SettingRepository $settings)
    {
        $this->settings = $settings;
    }

    public function isSlaSchedulerEnabled(): bool
    {
        return $this->isEnabled('pet_sla_scheduler_enabled');
    }

    public function isWorkProjectionEnabled(): bool
    {
        return $this->isEnabled('pet_work_projection_enabled');
    }

    public function isQueueVisibilityEnabled(): bool
    {
        return $this->isEnabled('pet_queue_visibility_enabled');
    }

    public function isPriorityEngineEnabled(): bool
    {
        return $this->isEnabled('pet_priority_engine_enabled');
    }

    private function isEnabled(string $key): bool
    {
        $setting = $this->settings->findByKey($key);
        if (!$setting) {
            return false;
        }

        $value = filter_var($setting->value(), FILTER_VALIDATE_BOOLEAN);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[PET FeatureFlag] %s: %s', $key, $value ? 'ENABLED' : 'DISABLED'));
        }

        return $value;
    }
}
