<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class VideoDurationService
{
    public function validateMaxDuration(UploadedFile $file, int $maxSeconds): ?string
    {
        $ffprobe = (new ExecutableFinder())->find('ffprobe');
        if ($ffprobe === null) {
            return 'Video validation is unavailable because ffprobe is not installed on the server.';
        }

        $process = new Process([
            $ffprobe,
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $file->getRealPath(),
        ]);

        try {
            $process->mustRun();
        } catch (ExceptionInterface $exception) {
            return 'Unable to read video duration.';
        }

        $duration = (float) trim($process->getOutput());
        if ($duration <= 0) {
            return 'Unable to read video duration.';
        }

        if ($duration > $maxSeconds) {
            return 'Video duration must not exceed ' . $maxSeconds . ' seconds.';
        }

        return null;
    }
}
