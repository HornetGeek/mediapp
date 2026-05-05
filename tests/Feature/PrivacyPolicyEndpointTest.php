<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyEndpointTest extends TestCase
{
    public function test_privacy_policy_endpoint_returns_policy_content(): void
    {
        $response = $this->getJson('/api/privacy-policy');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Privacy Policy Found')
            ->assertJsonPath('data.title', 'Privacy Policy for Medical Visits Scheduling Application (Egypt-Compliant)')
            ->assertJsonPath('data.effective_date', '[Insert Date]');

        $content = $response->json('data.content');

        $this->assertStringContainsString('Egypt Personal Data Protection Law No. 151 of 2020', $content);
        $this->assertStringContainsString('By using the App, you confirm your explicit consent', $content);
    }
}
