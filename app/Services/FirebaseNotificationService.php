<?php
namespace App\Services;

use Google_Client;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebaseNotificationService
{
    protected $client;
    protected $credentialsPath;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->credentialsPath = config('firebase.projects.app.credentials.file')
            ?: storage_path('app/firebase/medicalapp-bf9bc-26c0d1be71dd.json');

        try {
            if (!empty($this->credentialsPath) && file_exists($this->credentialsPath)) {
                $this->client->setAuthConfig($this->credentialsPath);
            } else {
                Log::error('Firebase credentials file not found', [
                    'credentials_path' => $this->credentialsPath,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Failed to initialize Firebase client credentials', [
                'credentials_path' => $this->credentialsPath,
                'error' => $e->getMessage(),
            ]);
        }

        $this->client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    }

    public function sendNotification($deviceToken, $title, $body, $data = [])
    {
        if (empty($deviceToken)) {
            Log::warning('Skipping FCM notification because device token is empty');
            return null;
        }

        $projectId = $this->resolveProjectId();
        if (empty($projectId)) {
            Log::error('Unable to send FCM notification: missing project id');
            return null;
        }

        Log::info('Sending FCM', [
            'token' => $this->maskToken($deviceToken),
            'title' => $title,
            'project_id' => $projectId,
        ]);

        $httpClient = new Client();

        try {
            $tokenPayload = $this->client->fetchAccessTokenWithAssertion();
            $accessToken = $tokenPayload['access_token'] ?? null;

            if (empty($accessToken)) {
                Log::error('Unable to send FCM notification: missing access token from Google client', [
                    'token_payload' => $tokenPayload,
                ]);
                return null;
            }
        } catch (Throwable $e) {
            Log::error('Unable to fetch Google access token for FCM', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = json_encode($value);
                } else {
                    $data[$key] = (string) $value;
                }
            }
        }

        $message = [
            'message' => [
                'token' => (string) $deviceToken,
                'notification' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                ],
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = $data;
        }

        try {
            $response = $httpClient->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error('FCM client error', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody()?->getContents(),
            ]);
            return null;
        } catch (Throwable $e) {
            Log::error('FCM unexpected error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return json_decode((string) $response->getBody(), true);
    }

    private function resolveProjectId(): ?string
    {
        $projectId = env('FIREBASE_PROJECT_ID');
        if (!empty($projectId)) {
            return $projectId;
        }

        $credentialsProjectId = $this->extractProjectIdFromCredentials();
        if (!empty($credentialsProjectId)) {
            return $credentialsProjectId;
        }

        $fallback = env('FIREBASE_PROJECT');
        if (!empty($fallback) && $fallback !== 'app') {
            return $fallback;
        }

        return null;
    }

    private function extractProjectIdFromCredentials(): ?string
    {
        if (empty($this->credentialsPath) || !file_exists($this->credentialsPath)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($this->credentialsPath), true);
        } catch (Throwable $e) {
            Log::error('Unable to read firebase credentials file for project id', [
                'credentials_path' => $this->credentialsPath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $projectId = $decoded['project_id'] ?? null;
        return is_string($projectId) && $projectId !== '' ? $projectId : null;
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 10) {
            return $token;
        }

        return substr($token, 0, 6) . '...' . substr($token, -4);
    }

}
