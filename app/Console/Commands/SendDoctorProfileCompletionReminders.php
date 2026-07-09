<?php

namespace App\Console\Commands;

use App\Models\Doctors;
use App\Services\DoctorProfileCompletionReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDoctorProfileCompletionReminders extends Command
{
    protected $signature = 'doctor:profile-completion-reminders';

    protected $description = 'Send profile completion reminders to doctors missing required profile data';

    public function handle(DoctorProfileCompletionReminderService $reminderService): int
    {
        $sentCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $stageCounts = [];

        Log::info('Cron doctor:profile-completion-reminders started', [
            'started_at' => now()->toDateTimeString(),
        ]);

        Doctors::query()
            ->orderBy('id')
            ->chunkById(100, function ($doctors) use ($reminderService, &$sentCount, &$skippedCount, &$failedCount, &$stageCounts) {
                foreach ($doctors as $doctor) {
                    try {
                        $result = $reminderService->sendReminderIfNeeded($doctor);
                    } catch (Throwable $exception) {
                        $failedCount++;
                        Log::error('Doctor profile completion reminder failed', [
                            'doctor_id' => (int) $doctor->id,
                            'error' => $exception->getMessage(),
                        ]);
                        continue;
                    }

                    if ($result === null) {
                        $skippedCount++;
                        continue;
                    }

                    $sentCount++;
                    $stage = $result['stage'];
                    $stageCounts[$stage] = ($stageCounts[$stage] ?? 0) + 1;
                }
            });

        Log::info('Cron doctor:profile-completion-reminders finished', [
            'sent_count' => $sentCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'stage_counts' => $stageCounts,
            'finished_at' => now()->toDateTimeString(),
        ]);

        $this->info(sprintf(
            'Doctor profile completion reminders sent: %d, skipped: %d, failed: %d.',
            $sentCount,
            $skippedCount,
            $failedCount
        ));

        return self::SUCCESS;
    }
}
