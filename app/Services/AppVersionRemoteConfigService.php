<?php

namespace App\Services;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\RemoteConfig;
use Kreait\Firebase\RemoteConfig\Parameter;
use Throwable;

class AppVersionRemoteConfigService
{
    private const PARAMETER_MAP = [
        AppVersion::APP_COMPANY => [
            AppVersion::PLATFORM_ANDROID => [
                'version' => 'AppVersionCompAndroid',
                'forced' => 'IsForceUpdateCompAndroid',
            ],
            AppVersion::PLATFORM_IOS => [
                'version' => 'AppVersionCompIos',
                'forced' => 'IsForceUpdateCompIos',
            ],
        ],
        AppVersion::APP_DOCTOR => [
            AppVersion::PLATFORM_ANDROID => [
                'version' => 'AppVersionDrAndroid',
                'forced' => 'IsForceUpdateDrAndroid',
            ],
            AppVersion::PLATFORM_IOS => [
                'version' => 'AppVersionDrIos',
                'forced' => 'IsForceUpdateDrIos',
            ],
        ],
    ];

    public function __construct(private readonly RemoteConfig $remoteConfig)
    {
    }

    public function getRule(string $appType, string $platform): ?array
    {
        $keys = self::PARAMETER_MAP[$appType][$platform] ?? null;

        if (!$keys) {
            return null;
        }

        try {
            $parameters = $this->remoteConfig->get()->parameters();
            $version = $this->parameterValue($parameters[$keys['version']] ?? null);

            if ($version === null || $version === '') {
                return null;
            }

            return [
                'version' => $version,
                'is_forced' => $this->toBoolean($this->parameterValue($parameters[$keys['forced']] ?? null)),
                'platform' => $platform,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to read app versions from Firebase Remote Config', [
                'app_type' => $appType,
                'platform' => $platform,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function applyToDashboardValues(array $versions, array $forced): array
    {
        foreach (self::PARAMETER_MAP as $appType => $platforms) {
            foreach (array_keys($platforms) as $platform) {
                $rule = $this->getRule($appType, $platform);

                if (!$rule) {
                    continue;
                }

                $versions[$appType][$platform] = $rule['version'];
                $forced[$appType][$platform] = (int) $rule['is_forced'];
            }
        }

        return [$versions, $forced];
    }

    public function publishFromDashboardPayload(array $apps): bool
    {
        try {
            $template = $this->remoteConfig->get();

            foreach (self::PARAMETER_MAP as $appType => $platforms) {
                foreach ($platforms as $platform => $keys) {
                    $platformData = $apps[$appType][$platform] ?? null;

                    if (!$platformData) {
                        continue;
                    }

                    $template = $template
                        ->withParameter(Parameter::named($keys['version'], (string) $platformData['version']))
                        ->withParameter(Parameter::named($keys['forced'], $this->booleanString($platformData['is_forced'])));
                }
            }

            $this->remoteConfig->publish($template);

            return true;
        } catch (Throwable $exception) {
            Log::error('Unable to publish app versions to Firebase Remote Config', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function parameterValue(?Parameter $parameter): ?string
    {
        if (!$parameter || !$parameter->defaultValue()) {
            return null;
        }

        $value = $parameter->defaultValue()->toArray();

        return array_key_exists('value', $value) ? (string) $value['value'] : null;
    }

    private function toBoolean(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function booleanString(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }
}
