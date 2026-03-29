<?php

namespace Tests\Unit\Subscriptions;

use App\Models\Package;
use App\Services\SubscriptionPlanService;
use Carbon\Carbon;
use Tests\TestCase;

class SubscriptionPlanServiceTest extends TestCase
{
    public function test_it_calculates_subscription_end_date_for_standard_calendar_plans(): void
    {
        $service = app(SubscriptionPlanService::class);
        $startDate = Carbon::parse('2026-01-31');

        $quarterly = new Package([
            'duration' => 90,
            'plan_type' => Package::PLAN_QUARTERLY,
            'billing_months' => 3,
        ]);

        $semiAnnual = new Package([
            'duration' => 180,
            'plan_type' => Package::PLAN_SEMI_ANNUAL,
            'billing_months' => 6,
        ]);

        $annual = new Package([
            'duration' => 365,
            'plan_type' => Package::PLAN_ANNUAL,
            'billing_months' => 12,
        ]);

        $this->assertSame('2026-04-30', $service->calculateSubscriptionEndDate($startDate, $quarterly)->toDateString());
        $this->assertSame('2026-07-31', $service->calculateSubscriptionEndDate($startDate, $semiAnnual)->toDateString());
        $this->assertSame('2027-01-31', $service->calculateSubscriptionEndDate($startDate, $annual)->toDateString());
    }

    public function test_it_uses_legacy_day_based_fallback_for_custom_day_plans(): void
    {
        $service = app(SubscriptionPlanService::class);
        $startDate = Carbon::parse('2026-03-10');

        $customDays = new Package([
            'duration' => 45,
            'plan_type' => Package::PLAN_CUSTOM_DAYS,
            'billing_months' => null,
        ]);

        $this->assertSame('2026-04-24', $service->calculateSubscriptionEndDate($startDate, $customDays)->toDateString());
    }

    public function test_it_normalizes_package_payload_for_standard_and_legacy_api_inputs(): void
    {
        $service = app(SubscriptionPlanService::class);

        $standard = $service->normalizePackageAttributes([
            'plan_type' => Package::PLAN_QUARTERLY,
        ]);

        $legacy = $service->normalizePackageAttributes([
            'duration' => 120,
        ]);

        $this->assertSame(Package::PLAN_QUARTERLY, $standard['plan_type']);
        $this->assertSame(3, $standard['billing_months']);
        $this->assertSame(90, $standard['duration']);

        $this->assertSame(Package::PLAN_CUSTOM_DAYS, $legacy['plan_type']);
        $this->assertNull($legacy['billing_months']);
        $this->assertSame(120, $legacy['duration']);
    }
}
