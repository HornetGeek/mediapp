<?php

namespace App\Services;

use Google\Client as GoogleClient;

class GoogleIdTokenVerifier
{
    public function verify(string $idToken): ?array
    {
        $clientIds = array_values(array_filter((array) config('services.google.client_ids', [])));
        if (empty($clientIds)) {
            return null;
        }

        foreach ($clientIds as $clientId) {
            $client = new GoogleClient(['client_id' => $clientId]);
            try {
                $payload = $client->verifyIdToken($idToken);
            } catch (\Throwable $exception) {
                continue;
            }

            if (is_array($payload)) {
                return $payload;
            }
        }

        return null;
    }
}
