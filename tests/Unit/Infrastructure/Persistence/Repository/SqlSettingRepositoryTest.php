<?php

declare(strict_types=1);

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public function prepare($query, ...$args) { return $query; }
            public function get_row($query) { return null; }
            public function get_results($query) { return []; }
            public function get_var($query) { return null; }
            public function insert($table, $data, $format = null) { return 1; }
            public function replace($table, $data, $format = null) { return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
        }
    }
}

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository {

    use PHPUnit\Framework\TestCase;
    use Pet\Infrastructure\Persistence\Repository\SqlSettingRepository;
    use Pet\Domain\Configuration\Entity\Setting;

    class SqlSettingRepositoryTest extends TestCase
    {
        public function testSaveAndFind()
        {
            $wpdb = $this->createMock(\wpdb::class);
            $wpdb->prefix = 'wp_';
            
            $repo = new SqlSettingRepository($wpdb);
            
            $setting = new Setting(
                'site_name',
                'My PET Site',
                'string',
                'The site name'
            );
            
            $wpdb->expects($this->once())
                 ->method('replace')
                 ->with(
                     $this->stringContains('pet_settings'),
                     $this->callback(function($data) {
                         return $data['setting_key'] === 'site_name' && $data['setting_value'] === 'My PET Site';
                     })
                 );
                 
            $repo->save($setting);
        }

        public function testFindByKey()
        {
            $wpdb = $this->createMock(\wpdb::class);
            $wpdb->prefix = 'wp_';
            
            $row = (object) [
                'setting_key' => 'site_name',
                'setting_value' => 'My PET Site',
                'setting_type' => 'string',
                'description' => 'The site name',
                'updated_at' => '2023-01-01 12:00:00'
            ];

            $wpdb->expects($this->once())
                 ->method('prepare')
                 ->willReturn('SELECT ...');
                 
            $wpdb->expects($this->once())
                 ->method('get_row')
                 ->willReturn($row);

            $repo = new SqlSettingRepository($wpdb);
            $setting = $repo->findByKey('site_name');

            $this->assertNotNull($setting);
            $this->assertEquals('site_name', $setting->key());
            $this->assertEquals('My PET Site', $setting->value());
        }
    }
}
