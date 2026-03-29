<?php

namespace App\Services;

use App\Models\Package;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class SubscriptionPlanService
{
    public function normalizePackageAttributes(array $data): array
    {
        $planType = $data['plan_type'] ?? null;
        $duration = isset($data['duration']) ? (int) $data['duration'] : null;

        if ($planType !== null && Package::isStandardPlan($planType)) {
            return [
                'plan_type' => $planType,
                'billing_months' => Package::PLAN_TO_MONTHS[$planType],
                'duration' => Package::PLAN_TO_DAYS[$planType],
            ];
        }

        if ($duration !== null && $duration > 0) {
            return [
                'plan_type' => Package::PLAN_CUSTOM_DAYS,
                'billing_months' => null,
                'duration' => $duration,
            ];
        }

        throw new InvalidArgumentException('Either a valid plan type or a positive duration is required.');
    }

    public function calculateSubscriptionEndDate(CarbonInterface $subscriptionStart, Package $package): Carbon
    {
        $startDate = Carbon::parse($subscriptionStart);
        $billingMonths = $package->resolveBillingMonths();

        if ($billingMonths !== null) {
            return $startDate->copy()->addMonthsNoOverflow($billingMonths);
        }

        $duration = (int) $package->duration;

        if ($duration < 1) {
            throw new InvalidArgumentException('Custom day-based plans must have duration greater than zero.');
        }

        return $startDate->copy()->addDays($duration);
    }
}
