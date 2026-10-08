<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_day_and_fresh_check_out_counts_are_scoped_to_the_gym(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-09 01:30:00', 'Asia/Karachi'));
        $gym = Gym::create(['name' => 'Gym', 'slug' => 'gym', 'email' => 'gym@test.local']);
        $other = Gym::create(['name' => 'Other', 'slug' => 'other', 'email' => 'other@test.local']);
        $user = User::create(['gym_id' => $gym->id, 'name' => 'Ali', 'email' => 'ali@test.local', 'password' => 'x']);
        $record = Attendance::create([
            'gym_id' => $gym->id, 'user_id' => $user->id, 'source' => 'biometric',
            'device_user_id' => '14', 'check_in_time' => '2026-10-09 01:18:00',
        ]);
        $service = app(DashboardService::class);
        $stats = $service->getStats($gym->id);
        $this->assertSame(1, $stats['today_checkins']);
        $this->assertSame(1, $stats['checked_in']);
        $this->assertSame(0, $stats['checked_out']);
        $record->update(['check_out_time' => '2026-10-09 01:24:06']);
        $stats = $service->getStats($gym->id);
        $this->assertSame(0, $stats['checked_in']);
        $this->assertSame(1, $stats['checked_out']);
        $this->assertSame(0, $service->getStats($other->id)['today_checkins']);
        $this->assertSame(1, $service->getStats(null)['today_checkins']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-10 01:30:00', 'Asia/Karachi'));
        $this->assertSame(0, $service->getStats($gym->id)['today_checkins']);
        $this->travelBack();
    }
}
