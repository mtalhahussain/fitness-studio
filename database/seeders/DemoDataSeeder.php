<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\MemberTrainingPeriod;
use App\Models\Payment;
use App\Models\TrainerCommission;
use App\Models\TrainerCommissionConfig;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private int $gymId;
    private array $invCounters = [];

    public function run(): void
    {
        // ── Gym ────────────────────────────────────────────────────────
        $gym = Gym::updateOrCreate(
            ['slug' => 'demo-gym'],
            [
                'name'     => 'PowerFit Studio',
                'email'    => 'info@powerfitdemo.pk',
                'phone'    => '+92-21-3456-7890',
                'address'  => 'Plot 15, Block 4, Gulshan-e-Iqbal',
                'city'     => 'Karachi',
                'country'  => 'Pakistan',
                'status'   => 'active',
                'currency' => 'PKR',
            ]
        );
        $this->gymId = $gym->id;

        // ── Users ───────────────────────────────────────────────────────
        $owner   = $this->makeUser('owner@demogym.com',   'Ahmad Khan',    'male',   '1980-05-12', '+92-300-1111111', 'owner');
        $trainer = $this->makeUser('trainer@demogym.com', 'Bilal Hussain', 'male',   '1990-03-22', '+92-321-2222222', 'trainer');

        $members = [
            $this->makeUser('member@demogym.com',      'Sara Ahmed',    'female', '1995-08-15', '+92-333-3333333', 'member'),
            $this->makeUser('usman.demo@demogym.com',  'Usman Ali',     'male',   '1988-11-30', '+92-311-4444444', 'member'),
            $this->makeUser('fatima.demo@demogym.com', 'Fatima Sheikh', 'female', '1992-04-17', '+92-345-5555555', 'member'),
            $this->makeUser('hassan.demo@demogym.com', 'Hassan Malik',  'male',   '1997-07-08', '+92-303-6666666', 'member'),
            $this->makeUser('ayesha.demo@demogym.com', 'Ayesha Riaz',   'female', '1993-12-25', '+92-315-7777777', 'member'),
            $this->makeUser('kamran.demo@demogym.com', 'Kamran Butt',   'male',   '1985-02-14', '+92-322-8888888', 'member'),
            $this->makeUser('zara.demo@demogym.com',   'Zara Qureshi',  'female', '1999-06-03', '+92-312-9999999', 'member'),
            $this->makeUser('omar.demo@demogym.com',   'Omar Siddiqui', 'male',   '1991-09-19', '+92-341-0000001', 'member'),
        ];

        // ── Trainer profile ─────────────────────────────────────────────
        DB::table('trainer_profiles')->updateOrInsert(
            ['user_id' => $trainer->id],
            [
                'gym_id'           => $this->gymId,
                'specialization'   => 'Strength & Conditioning',
                'bio'              => 'Certified personal trainer with 8 years of experience in strength training and sports conditioning.',
                'experience_years' => 8,
                'certifications'   => json_encode(['NASM-CPT', 'CrossFit Level 2', 'Nutrition Coach']),
                'hourly_rate'      => 2000.00,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        // ── Membership Plans ────────────────────────────────────────────
        $plans = $this->makePlans();

        // ── Memberships ─────────────────────────────────────────────────
        // [member, plan_key, started_days_ago, amount_paid, invoice_status_override]
        $assignments = [
            [$members[0], 'standard', 15,  4000,  null],             // active, fully paid
            [$members[1], 'premium',  20,  10000, null],             // active, fully paid
            [$members[2], 'basic',    25,  2500,  null],             // active, fully paid
            [$members[3], 'standard', 5,   2000,  'partially_paid'], // active, balance due
            [$members[4], 'yearly',   60,  35000, null],             // active, long-term paid
            [$members[5], 'basic',    35,  2500,  null],             // EXPIRED (basic = 30 days)
            [$members[6], 'standard', 12,  4000,  null],             // active, expiring soon
            [$members[7], 'premium',  8,   5000,  'partially_paid'], // active, partial payment
        ];

        foreach ($assignments as [$member, $planKey, $daysAgo, $amtPaid, $statusOverride]) {
            $this->makeMembership($member, $plans[$planKey], $daysAgo, $amtPaid, $statusOverride);
        }

        // ── Commission Config (40 % trainer share) ──────────────────────
        TrainerCommissionConfig::firstOrCreate(
            ['gym_id' => $this->gymId, 'trainer_id' => $trainer->id, 'effective_to' => null],
            [
                'commission_rate' => 40.00,
                'effective_from'  => Carbon::now()->subMonths(4)->toDateString(),
            ]
        );

        // ── Assign 4 members to trainer ─────────────────────────────────
        $assignedMembers = array_slice($members, 0, 4);
        $periods = [];

        foreach ($assignedMembers as $m) {
            DB::table('trainer_member')->updateOrInsert(
                ['gym_id' => $this->gymId, 'trainer_id' => $trainer->id, 'member_id' => $m->id],
                ['is_active' => true, 'assigned_at' => Carbon::now()->subDays(30)]
            );

            $periods[] = [
                $m,
                MemberTrainingPeriod::firstOrCreate(
                    ['gym_id' => $this->gymId, 'trainer_id' => $trainer->id, 'member_id' => $m->id, 'status' => 'active'],
                    [
                        'start_date' => Carbon::now()->subDays(rand(25, 38))->toDateString(),
                        'end_date'   => null,
                    ]
                ),
            ];
        }

        // ── Training Sessions ────────────────────────────────────────────
        $this->makeSessions($trainer, $assignedMembers);

        // ── Attendance (last 30 days for every member) ───────────────────
        foreach ($members as $m) {
            $this->makeAttendance($m);
        }

        // ── Commissions (previous + current month) ───────────────────────
        foreach ($periods as [$member, $period]) {
            $this->makeCommission($trainer, $member, $period);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeUser(string $email, string $name, string $gender, string $dob, string $phone, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'gym_id'        => $this->gymId,
                'name'          => $name,
                'gender'        => $gender,
                'date_of_birth' => $dob,
                'phone'         => $phone,
                'password'      => Hash::make('password'),
                'status'        => 'active',
            ]
        );
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
        return $user;
    }

    private function makePlans(): array
    {
        $defs = [
            'basic'    => ['Basic Monthly',     'monthly',  30,  2500,  'Gym floor access + locker room'],
            'standard' => ['Standard Monthly',  'monthly',  30,  4000,  'Basic + group classes + 1 trainer consultation/month'],
            'premium'  => ['Premium Quarterly', 'quarterly',90,  10000, 'Standard + unlimited PT sessions + nutrition coaching'],
            'yearly'   => ['Elite Annual',      'yearly',   365, 35000, 'All-inclusive: PT, nutrition, priority booking, merchandise'],
        ];

        $plans = [];
        foreach ($defs as $key => [$name, $type, $days, $price, $desc]) {
            $plans[$key] = MembershipPlan::firstOrCreate(
                ['gym_id' => $this->gymId, 'name' => $name],
                ['type' => $type, 'duration_days' => $days, 'price' => $price, 'description' => $desc, 'is_active' => true]
            );
        }
        return $plans;
    }

    private function makeMembership(User $member, MembershipPlan $plan, int $daysAgo, int $amtPaid, ?string $statusOverride): void
    {
        if (Membership::where('user_id', $member->id)->where('plan_id', $plan->id)->exists()) {
            return;
        }

        $startDate  = Carbon::now()->subDays($daysAgo)->startOfDay();
        $endDate    = $startDate->copy()->addDays($plan->duration_days);
        $isExpired  = $endDate->lt(Carbon::now());
        $isFullPaid = $amtPaid >= $plan->price;

        $invoiceStatus = $statusOverride ?? ($isFullPaid ? 'paid' : 'partially_paid');

        // Invoice number: use start month + D-prefix to never clash with real sequential numbers
        $monthKey = $startDate->format('Ym');
        $this->invCounters[$monthKey] = ($this->invCounters[$monthKey] ?? 0) + 1;
        $invNumber = 'INV-' . $monthKey . '-D' . str_pad($this->invCounters[$monthKey], 3, '0', STR_PAD_LEFT);

        while (Invoice::withoutGlobalScopes()->where('invoice_number', $invNumber)->exists()) {
            $this->invCounters[$monthKey]++;
            $invNumber = 'INV-' . $monthKey . '-D' . str_pad($this->invCounters[$monthKey], 3, '0', STR_PAD_LEFT);
        }

        $invoice = Invoice::create([
            'gym_id'          => $this->gymId,
            'user_id'         => $member->id,
            'invoice_number'  => $invNumber,
            'subtotal'        => $plan->price,
            'tax_amount'      => 0,
            'discount_amount' => 0,
            'total_amount'    => $plan->price,
            'status'          => $invoiceStatus,
            'due_date'        => $startDate->copy()->addDays(3)->toDateString(),
            'paid_at'         => $isFullPaid ? $startDate->copy()->addHours(1) : null,
            'created_at'      => $startDate,
            'updated_at'      => $startDate,
        ]);

        DB::table('invoice_items')->insert([
            'invoice_id' => $invoice->id,
            'item_type'  => 'plan',
            'item_id'    => $plan->id,
            'name'       => $plan->name . ' Membership',
            'unit_price' => $plan->price,
            'quantity'   => 1,
            'subtotal'   => $plan->price,
            'created_at' => $startDate,
            'updated_at' => $startDate,
        ]);

        if ($amtPaid > 0) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'gym_id'     => $this->gymId,
                'amount'     => $amtPaid,
                'method'     => collect(['cash', 'card', 'bank_transfer', 'wallet'])->random(),
                'paid_at'    => $startDate->copy()->addHours(1),
                'created_at' => $startDate,
                'updated_at' => $startDate,
            ]);
        }

        Membership::create([
            'gym_id'      => $this->gymId,
            'user_id'     => $member->id,
            'plan_id'     => $plan->id,
            'invoice_id'  => $invoice->id,
            'start_date'  => $startDate->toDateString(),
            'end_date'    => $endDate->toDateString(),
            'status'      => $isExpired ? 'expired' : 'active',
            'amount_paid' => $amtPaid,
            'created_at'  => $startDate,
            'updated_at'  => $startDate,
        ]);
    }

    private function makeSessions(User $trainer, array $members): void
    {
        $titles = [
            'Strength & Power', 'Upper Body Strength', 'Lower Body Blast', 'Full Body Compound',
            'HIIT Cardio', 'Endurance Run', 'Cardio Circuit', 'Fat Burn Session',
            'Functional Fitness', 'Core Stability', 'Athletic Conditioning', 'Movement Patterns',
        ];

        // Past sessions (completed)
        $pastDays = [28, 25, 22, 19, 16, 14, 12, 9, 7, 5, 3, 2];
        foreach ($pastDays as $i => $daysAgo) {
            $member = $members[$i % count($members)];
            TrainingSession::firstOrCreate(
                [
                    'gym_id'       => $this->gymId,
                    'trainer_id'   => $trainer->id,
                    'member_id'    => $member->id,
                    'scheduled_at' => Carbon::now()->subDays($daysAgo)->setTime(rand(7, 19), 0, 0),
                ],
                [
                    'title'         => $titles[$i % count($titles)],
                    'session_type'  => 'personal',
                    'duration_mins' => collect([45, 60, 75, 90])->random(),
                    'status'        => 'completed',
                ]
            );
        }

        // Upcoming sessions (scheduled)
        $futureDays = [1, 3, 5, 8, 12];
        foreach ($futureDays as $i => $daysAhead) {
            $member = $members[$i % count($members)];
            TrainingSession::firstOrCreate(
                [
                    'gym_id'       => $this->gymId,
                    'trainer_id'   => $trainer->id,
                    'member_id'    => $member->id,
                    'scheduled_at' => Carbon::now()->addDays($daysAhead)->setTime(rand(7, 19), 0, 0),
                ],
                [
                    'title'         => $titles[($i + 4) % count($titles)],
                    'session_type'  => 'personal',
                    'duration_mins' => 60,
                    'status'        => 'scheduled',
                ]
            );
        }
    }

    private function makeAttendance(User $member): void
    {
        $checkInHours = [6, 7, 8, 9, 17, 18, 19, 20];
        $durations    = [45, 55, 60, 70, 75, 80, 90];

        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            if (rand(0, 99) >= 55) continue; // ~55 % = ~3-4 days/week

            $date = Carbon::now()->subDays($daysAgo)->startOfDay();

            if (Attendance::where('user_id', $member->id)->whereDate('check_in_time', $date->toDateString())->exists()) {
                continue;
            }

            $checkIn = $date->copy()
                ->setHour($checkInHours[array_rand($checkInHours)])
                ->setMinute(rand(0, 45));

            Attendance::create([
                'gym_id'         => $this->gymId,
                'user_id'        => $member->id,
                'check_in_time'  => $checkIn,
                'check_out_time' => $checkIn->copy()->addMinutes($durations[array_rand($durations)]),
                'source'         => rand(0, 1) ? 'manual' : 'biometric',
                'created_at'     => $checkIn,
                'updated_at'     => $checkIn,
            ]);
        }
    }

    private function makeCommission(User $trainer, User $member, MemberTrainingPeriod $period): void
    {
        // Commission needs a real payment + invoice reference
        $membership = Membership::where('user_id', $member->id)->where('gym_id', $this->gymId)->first();
        if (! $membership?->invoice_id) return;

        $payment = Payment::where('invoice_id', $membership->invoice_id)->first();
        if (! $payment) return; // member has no payment — skip

        $rate = 40.00;

        foreach ([Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->startOfMonth()] as $monthDate) {
            $periodMonth = $monthDate->toDateString(); // YYYY-MM-01 (date column)

            if (TrainerCommission::where('trainer_id', $trainer->id)->where('member_id', $member->id)->where('period_month', $periodMonth)->exists()) {
                continue;
            }

            $total    = collect([3500, 4000, 4500, 5000])->random();
            $trShare  = round($total * $rate / 100, 2);
            $gymShare = round($total - $trShare, 2);
            $isPrev   = $monthDate->lt(Carbon::now()->startOfMonth());

            TrainerCommission::create([
                'gym_id'             => $this->gymId,
                'trainer_id'         => $trainer->id,
                'member_id'          => $member->id,
                'payment_id'         => $payment->id,
                'invoice_id'         => $membership->invoice_id,
                'training_period_id' => $period->id,
                'total_amount'       => $total,
                'trainer_share'      => $trShare,
                'gym_share'          => $gymShare,
                'commission_rate'    => $rate,
                'period_month'       => $periodMonth,
                'status'             => $isPrev ? 'paid' : 'pending',
                'paid_at'            => $isPrev ? $monthDate->copy()->endOfMonth() : null,
            ]);
        }
    }
}
