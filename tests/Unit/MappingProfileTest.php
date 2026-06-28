<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Normalization\MappingProfileRegistry;
use App\Normalization\Profiles\FirebirdFinanceProfile;
use SCHF\SDK\Normalization\MappingProfile;
use SCHF\SDK\Normalization\MappingRule;

class MappingProfileTest extends TestCase
{
    private MappingProfileRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new MappingProfileRegistry();
    }

    public function test_registers_and_retrieves_profile(): void
    {
        $profile = new MappingProfile(
            source_type:  'firebird',
            profile:      'test-profile',
            version:      '1.0.0',
            source_table: 'TEST_TABLE',
            target_class: 'stdClass',
            target_entity: 'test',
            rules:        [],
        );

        $this->registry->register($profile);

        $retrieved = $this->registry->get('firebird', 'test-profile');
        $this->assertInstanceOf(MappingProfile::class, $retrieved);
        $this->assertSame('TEST_TABLE', $retrieved->source_table);
    }

    public function test_returns_null_for_unknown_profile(): void
    {
        $this->assertNull($this->registry->get('firebird', 'nonexistent'));
    }

    public function test_firebird_profiles_are_loadable(): void
    {
        $profiles = FirebirdFinanceProfile::all();

        $this->assertCount(3, $profiles);

        foreach ($profiles as $profile) {
            $this->assertInstanceOf(MappingProfile::class, $profile);
            $this->assertSame('firebird', $profile->source_type);
            $this->assertNotEmpty($profile->rules);
        }
    }

    public function test_apply_mapping_rules(): void
    {
        $profile = new MappingProfile(
            source_type:  'test',
            profile:      'apply-test',
            version:      '1.0.0',
            source_table: 'SRC',
            target_class: 'stdClass',
            target_entity: 'test',
            rules: [
                new MappingRule(
                    source_column: 'COL_A',
                    target_field:  'field_a',
                    transform:     'trim',
                ),
                new MappingRule(
                    source_column: 'COL_B',
                    target_field:  'field_b',
                    transform:     'upper',
                ),
            ],
        );

        $row = ['COL_A' => '  hello  ', 'COL_B' => 'world'];

        $result = $this->registry->apply($profile, $row);

        $this->assertSame('hello', $result['field_a']);
        $this->assertSame('WORLD', $result['field_b']);
    }

    public function test_apply_with_default_value(): void
    {
        $profile = new MappingProfile(
            source_type:  'test',
            profile:      'default-test',
            version:      '1.0.0',
            source_table: 'SRC',
            target_class: 'stdClass',
            target_entity: 'test',
            rules: [
                new MappingRule(
                    source_column: 'MISSING',
                    target_field:  'result',
                    default:       'fallback',
                ),
            ],
        );

        $row = ['OTHER' => 'x'];
        $result = $this->registry->apply($profile, $row);

        $this->assertSame('fallback', $result['result']);
    }

    public function test_date_normalization(): void
    {
        $profile = new MappingProfile(
            source_type:  'test',
            profile:      'date-test',
            version:      '1.0.0',
            source_table: 'SRC',
            target_class: 'stdClass',
            target_entity: 'test',
            rules: [
                new MappingRule(
                    source_column: 'DT',
                    target_field:  'date',
                    transform:     'date',
                ),
            ],
        );

        $result = $this->registry->apply($profile, ['DT' => '15/03/2025']);
        $this->assertSame('2025-03-15', $result['date']);

        $result = $this->registry->apply($profile, ['DT' => '2025-03-15']);
        $this->assertSame('2025-03-15', $result['date']);
    }
}
