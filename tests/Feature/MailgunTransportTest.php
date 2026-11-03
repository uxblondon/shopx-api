<?php

namespace Tests\Feature;

use Illuminate\Mail\MailManager;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Tests\TestCase;

class MailgunTransportTest extends TestCase
{
    private function manager(MockHttpClient $client): MailManager
    {
        config(['services.mailgun.domain' => 'web.example.test', 'services.mailgun.secret' => 'fixture-key',
            'mail.from.address' => 'orders@web.example.test', 'mail.from.name' => 'Trinity House']);
        $manager = new class($this->app) extends MailManager {
            public MockHttpClient $testClient;
            protected function getHttpClient(array $config) { return $this->testClient; }
        };
        $manager->testClient = $client;
        return $manager;
    }

    public function test_laravel_mailgun_configuration_sends_over_https_with_an_api_key(): void
    {
        config(['services.mailgun.endpoint' => 'api.mailgun.net']);
        $called = 0;
        $manager = $this->manager(new MockHttpClient(function ($method, $url, $options) use (&$called) {
            $called++;
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailgun.net/v3/web.example.test/messages.mime', $url);
            $this->assertStringNotContainsString('fixture-key', $url);
            $this->assertStringContainsString('Basic '.base64_encode('api:fixture-key'), implode("\n", $options['headers']));
            return new MockResponse(json_encode(['id' => '<fixture@mailgun.example.test>', 'message' => 'Queued. Thank you.']), ['http_code' => 200]);
        }));
        $mailer = $manager->mailer('mailgun');
        $this->assertInstanceOf(MailgunHttpTransport::class, $mailer->getSymfonyTransport());
        $sent = $mailer->raw('Captured API test; no external message is sent.', function ($message) {
            $message->to('tester@example.test')->subject('Mailgun API fixture');
        });
        $this->assertNotNull($sent);
        $this->assertSame(1, $called);
    }

    public function test_api_rejection_is_reported_as_a_delivery_failure(): void
    {
        $manager = $this->manager(new MockHttpClient(new MockResponse('{"message":"Forbidden"}', ['http_code' => 403])));
        $this->expectException(TransportExceptionInterface::class);
        $manager->mailer('mailgun')->raw('Not delivered.', fn ($message) => $message->to('tester@example.test')->subject('Rejected fixture'));
    }

    public function test_configured_region_controls_the_https_endpoint(): void
    {
        config(['services.mailgun.endpoint' => 'api.eu.mailgun.net']);
        $manager = $this->manager(new MockHttpClient(function ($method, $url) {
            $this->assertSame('https://api.eu.mailgun.net/v3/web.example.test/messages.mime', $url);
            return new MockResponse('{"id":"<fixture@example.test>","message":"Queued"}', ['http_code' => 200]);
        }));
        $manager->mailer('mailgun')->raw('Captured region fixture.', fn ($message) => $message->to('tester@example.test')->subject('Region fixture'));
    }
}
