<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Public working hours are now day-aware: a day can be marked closed (blank
 * value or the word "Closed") and the site then shows "Closed today" plus the
 * next open day's timing, while the full weekly schedule stays visible.
 */
class WeeklyHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_closed_day_reports_closed_today_and_the_next_open_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11')); // Sunday

        $raw = [
            'mon' => '8:00 AM - 8:00 PM',
            'tue' => '8:00 AM - 8:00 PM',
            'wed' => '8:00 AM - 8:00 PM',
            'thu' => '8:00 AM - 8:00 PM',
            'fri' => '8:00 AM - 8:00 PM',
            'sat' => '8:00 AM - 2:00 PM',
            'sun' => 'Closed',
        ];

        $today = hms_today_hours($raw);

        $this->assertTrue($today['closed']);
        $this->assertSame('Sunday', $today['label']);
        $this->assertSame('Monday', $today['next_label']);
        $this->assertSame('8:00 AM - 8:00 PM', $today['next_value']);
        $this->assertSame('Closed today · Mon 8:00 AM - 8:00 PM', hms_hours_summary($raw));
    }

    public function test_an_open_day_reports_its_own_timing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12')); // Monday

        $raw = ['mon' => '9:00 AM - 5:00 PM', 'sun' => 'Closed'];

        $today = hms_today_hours($raw);

        $this->assertFalse($today['closed']);
        $this->assertSame('9:00 AM - 5:00 PM', $today['value']);
        $this->assertSame('Open today: 9:00 AM - 5:00 PM', hms_hours_summary($raw));
    }

    public function test_blank_and_the_word_closed_both_mark_a_day_shut(): void
    {
        $hours = hms_weekly_hours([
            'mon' => '8:00 AM - 8:00 PM',
            'sun' => '',
            'sat' => 'closed',
        ]);

        $byKey = collect($hours)->keyBy('key');

        $this->assertFalse($byKey['mon']['closed']);
        $this->assertTrue($byKey['sat']['closed']);
        $this->assertTrue($byKey['sun']['closed']);
        $this->assertSame('Closed', $byKey['sun']['value']);
        $this->assertSame('8:00 AM - 8:00 PM', $byKey['mon']['value']);
    }

    public function test_when_a_day_is_closed_it_finds_the_next_open_day_across_the_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11')); // Sunday

        $raw = [
            'mon' => '8:00 AM - 8:00 PM',
            'sun' => 'Closed',
        ];

        $this->assertSame('Monday', hms_today_hours($raw)['next_label']);
    }

    public function test_it_falls_back_to_the_single_working_hours_string(): void
    {
        Settings::create(['key' => 'working_hours', 'value' => 'OPD 10:00 AM - 4:00 PM']);
        SiteContent::flush();

        $hours = hms_weekly_hours();

        $this->assertCount(7, $hours);
        $this->assertFalse(collect($hours)->contains(fn ($h) => $h['closed']));
        $this->assertSame('OPD 10:00 AM - 4:00 PM', $hours[0]['value']);
    }

    public function test_site_content_exposes_the_weekly_keys(): void
    {
        Settings::create(['key' => 'hours_mon', 'value' => '9:00 AM - 9:00 PM']);
        Settings::create(['key' => 'hours_sun', 'value' => 'Closed']);
        Settings::create(['key' => 'hours_note', 'value' => 'Emergency 24x7']);
        SiteContent::flush();

        $site = SiteContent::get();

        $this->assertSame('9:00 AM - 9:00 PM', $site['weekly_hours']['mon']);
        $this->assertSame('Closed', $site['weekly_hours']['sun']);
        $this->assertSame('Emergency 24x7', $site['hours_note']);
    }
}
