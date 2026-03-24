<?php
namespace App\Services;

use Google_Client;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig(storage_path('app/firebase/medicalapp-bf9bc-26c0d1be71dd.json'));
        $this->client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    }

    public function sendNotification($deviceToken, $title, $body, $data = [])
{
    Log::info('Sending FCM', [
    	'token' => $deviceToken,
    	'title' => $title,
	]);
    $httpClient = new Client();
    $accessToken = $this->client
        ->fetchAccessTokenWithAssertion()['access_token'];

    $url = 'https://fcm.googleapis.com/v1/projects/' 
        . env('FIREBASE_PROJECT_ID') 
        . '/messages:send';

    // تأكد أن كل القيم strings
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
            'token' => $deviceToken,
            'notification' => array_merge([
                'title' => (string) $title,
                'body'  => (string) $body,
            ], $data),
        ],
    ];
	
    try {
        $response = $httpClient->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
            'json' => $message,
        ]);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        Log::error('FCM Error: ' . $e->getResponse()->getBody()->getContents());
        return null;
    }

    return json_decode($response->getBody(), true);
}

}
