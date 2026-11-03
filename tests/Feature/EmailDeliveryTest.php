<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Mail\OrderNotification;
use App\Mail\PasswordResetLink;
use App\Models\Order;
use App\Models\OrderBilling;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'orders@web.example.test', 'mail.from.name' => 'Trinity House',
            'mail.order_notification_address' => 'admin@example.test', 'mail.test_recipient' => null,
            'mail.admin_url' => 'https://admin.staging.example.test']);
    }

    private function order(bool $paid = true): Order
    {
        $order = Order::create(['ref' => 'EMAIL-TEST', 'name' => 'Customer <script>alert(1)</script>',
            'email' => 'customer@example.test', 'status' => 'confirmed']);
        OrderPayment::create(['order_id' => $order->id, 'payment_type' => 'stripe', 'amount' => 10,
            'payment_id' => 'pi_emailFixture', 'payment_status' => $paid ? 'succeeded' : 'pending', 'payment_confirmed' => $paid]);
        OrderBilling::create(['order_id' => $order->id, 'name' => 'Test customer', 'address_line_1' => '1 Test Street',
            'city' => 'London', 'postcode' => 'SW1A 1AA', 'country_code' => 'GB', 'email' => 'customer@example.test']);
        return $order;
    }

    public function test_single_order_sends_both_messages_with_configured_sender_and_customer_reply_to(): void
    {
        $order = $this->order();
        $unrelated = $this->order();
        $this->artisan('send:emails', ['--order' => $order->id])->assertExitCode(0);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        $admin = $messages[0]->getOriginalMessage();
        $customer = $messages[1]->getOriginalMessage();
        $this->assertSame('orders@web.example.test', $admin->getFrom()[0]->getAddress());
        $this->assertSame('orders@web.example.test', $customer->getFrom()[0]->getAddress());
        $this->assertSame('customer@example.test', $admin->getReplyTo()[0]->getAddress());
        $this->assertSame('admin@example.test', $admin->getTo()[0]->getAddress());
        $this->assertSame('customer@example.test', $customer->getTo()[0]->getAddress());
        $this->assertStringContainsString('&lt;script&gt;', $customer->getHtmlBody());
        $this->assertStringNotContainsString('<script>', $customer->getHtmlBody());
        $this->assertNotNull($order->fresh()->email_confirmation_sent_at);
        $this->assertNotNull($order->fresh()->email_notification_sent_at);
        $this->assertNull($unrelated->fresh()->email_confirmation_sent_at);
        $this->artisan('send:emails', ['--order' => $order->id])->assertExitCode(0);
        $this->assertCount(2, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_transport_failure_keeps_messages_pending_and_returns_failure_without_logging_secrets(): void
    {
        $order = $this->order();
        Log::spy();
        Mail::shouldReceive('to')->twice()->andThrow(new \RuntimeException('private SMTP detail'));
        $this->artisan('send:emails', ['--order' => $order->id])->assertExitCode(1);
        $this->assertNull($order->fresh()->email_confirmation_sent_at);
        $this->assertNull($order->fresh()->email_notification_sent_at);
        Log::shouldHaveReceived('warning')->twice()->withArgs(fn ($message, $context) =>
            ! str_contains(json_encode([$message, $context]), 'private SMTP detail') && $context['record_id'] === $order->id);
    }

    public function test_successful_message_is_not_resent_when_only_other_message_is_pending(): void
    {
        $order = $this->order(); $order->update(['email_notification_sent_at' => now()]);
        Mail::fake();
        $this->artisan('send:emails', ['--order' => $order->id])->assertExitCode(0);
        Mail::assertSent(OrderConfirmation::class, 1);
        Mail::assertNotSent(OrderNotification::class);
        $this->assertNotNull($order->fresh()->email_confirmation_sent_at);
    }

    public function test_unverified_or_pending_orders_do_not_send_confirmation_emails(): void
    {
        $unpaid = $this->order(false);
        $pending = $this->order(); $pending->update(['status' => 'pending']);
        Mail::fake();
        $this->artisan('send:emails')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertNull($unpaid->fresh()->email_confirmation_sent_at);
        $this->assertNull($pending->fresh()->email_confirmation_sent_at);
    }

    public function test_single_order_test_skips_password_resets_and_redirects_both_recipients(): void
    {
        $order = $this->order();
        $user = User::factory()->create();
        $user->forceFill(['password_reset_token' => 'fixture-token', 'password_reset_token_created_at' => now()])->save();
        config(['mail.test_recipient' => 'tester@example.test']);
        Mail::fake();
        $this->artisan('send:emails', ['--order' => $order->id])->assertExitCode(0);
        Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->hasTo('tester@example.test'));
        Mail::assertSent(OrderNotification::class, fn ($mail) => $mail->hasTo('tester@example.test'));
        Mail::assertNotSent(PasswordResetLink::class);
        $this->assertNotNull($user->fresh()->password_reset_token_created_at);
    }

    public function test_reset_email_uses_configured_admin_url_and_only_clears_pending_timestamp_after_success(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password_reset_token' => 'fixture-token', 'password_reset_token_created_at' => now()])->save();
        $html = (new PasswordResetLink($user))->render();
        $this->assertStringContainsString('https://admin.staging.example.test/reset/fixture-token/password', $html);
        $this->assertStringNotContainsString('shopadmin.trinityhouse.co.uk', $html);
        $this->artisan('send:emails')->assertExitCode(0);
        $this->assertNull($user->fresh()->password_reset_token_created_at);
        $this->assertSame('fixture-token', $user->fresh()->password_reset_token);
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_reset_delivery_failure_preserves_pending_timestamp_for_retry(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password_reset_token' => 'fixture-token', 'password_reset_token_created_at' => now()])->save();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->artisan('send:emails')->assertExitCode(1);
        $this->assertNotNull($user->fresh()->password_reset_token_created_at);
    }
}
