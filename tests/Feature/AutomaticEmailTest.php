<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Mail\OrderNotification;
use App\Mail\PasswordResetLink;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderBilling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomaticEmailTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $date): Order
    {
        $order = Order::create(['ref' => 'AUTO-EMAIL', 'name' => 'Automation fixture', 'email' => 'customer@example.test',
            'status' => 'confirmed', 'created_at' => $date]);
        OrderPayment::create(['order_id' => $order->id, 'payment_type' => 'stripe', 'amount' => 10, 'payment_confirmed' => 1]);
        OrderBilling::create(['order_id' => $order->id, 'name' => 'Fixture', 'address_line_1' => '1 Test Street',
            'city' => 'London', 'postcode' => 'SW1A 1AA', 'country_code' => 'GB']);
        return $order;
    }

    private function enabled(int $after = 0): void
    {
        $this->app->instance('env', 'staging');
        config(['app.env' => 'staging', 'mail.automation.enabled' => true, 'mail.automation.mailer' => 'array',
            'mail.automation.since' => '2026-11-03 12:00:00', 'mail.automation.order_after_id' => $after,
            'mail.test_recipient' => 'tester@example.test']);
    }

    public function test_scheduled_mail_is_disabled_until_explicitly_enabled(): void
    {
        $order = $this->order('2026-11-03 13:00:00');
        config(['mail.automation.enabled' => false]); Mail::fake();
        $this->artisan('send:emails', ['--scheduled' => true])->assertExitCode(0);
        Mail::assertNothingSent(); $this->assertNull($order->fresh()->email_confirmation_sent_at);
    }

    public function test_cutoff_excludes_existing_orders_and_later_imports_with_old_dates(): void
    {
        $old = $this->order('2026-11-02 13:00:00');
        $existing = $this->order('2026-11-03 13:00:00');
        $new = $this->order('2026-11-03 13:00:00');
        $laterImport = $this->order('2026-11-01 13:00:00');
        $this->enabled($existing->id); Mail::fake();
        $this->artisan('send:emails', ['--scheduled' => true])->assertExitCode(0);
        Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->order->id === $new->id && $mail->hasTo('tester@example.test'));
        Mail::assertSent(OrderNotification::class, fn ($mail) => $mail->order->id === $new->id && $mail->hasTo('tester@example.test'));
        Mail::assertSentCount(2);
        foreach ([$old, $existing, $laterImport] as $order) {
            $this->assertNull($order->fresh()->email_confirmation_sent_at);
            $this->assertNull($order->fresh()->email_notification_sent_at);
        }
        $this->assertNotNull($new->fresh()->email_confirmation_sent_at);
        $this->artisan('send:emails', ['--scheduled' => true])->assertExitCode(0);
        Mail::assertSentCount(2);
    }

    public function test_only_new_reset_requests_are_sent_even_for_existing_accounts(): void
    {
        $old = User::factory()->create(); $new = User::factory()->create();
        $old->forceFill(['password_reset_token' => 'old-fixture', 'password_reset_token_created_at' => '2026-11-02 12:00:00'])->save();
        $new->forceFill(['created_at' => '2026-11-01 12:00:00', 'password_reset_token' => 'new-fixture',
            'password_reset_token_created_at' => '2026-11-03 12:01:00'])->save();
        $this->enabled(); Mail::fake();
        $this->artisan('send:emails', ['--scheduled' => true])->assertExitCode(0);
        Mail::assertSent(PasswordResetLink::class, fn ($mail) => $mail->user->id === $new->id && $mail->hasTo('tester@example.test'));
        Mail::assertSentCount(1);
        $this->assertNotNull($old->fresh()->password_reset_token_created_at);
        $this->assertNull($new->fresh()->password_reset_token_created_at);
    }

    public function test_dry_run_counts_messages_without_sending_or_changing_flags(): void
    {
        $order = $this->order('2026-11-03 12:00:00');
        $this->enabled(); Mail::fake();
        $this->artisan('send:emails', ['--scheduled' => true, '--dry-run' => true])
            ->expectsOutput('Eligible password-reset messages: 0')->expectsOutput('Eligible order messages: 2')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertNull($order->fresh()->email_confirmation_sent_at);
        $this->assertNull($order->fresh()->email_notification_sent_at);
    }

    public function test_staging_missing_recipient_or_invalid_cutoff_fails_before_sending(): void
    {
        $order = $this->order('2026-11-03 13:00:00');
        foreach ([['mail.test_recipient' => ''], ['mail.automation.since' => ''],
            ['mail.automation.since' => '2026-02-30 12:00:00'], ['mail.automation.order_after_id' => null],
            ['mail.automation.mailer' => 'smtp']] as $change) {
            $this->enabled(); config($change); Mail::fake();
            $this->artisan('send:emails', ['--scheduled' => true])->assertExitCode(1);
            Mail::assertNothingSent();
        }
        $this->assertNull($order->fresh()->email_confirmation_sent_at);
    }

    public function test_scheduled_send_uses_selected_transport_while_default_remains_log(): void
    {
        $order = $this->order('2026-11-03 13:00:00');
        $this->enabled(); config(['mail.default' => 'log']);
        $this->artisan('send:emails', ['--scheduled' => true])->assertExitCode(0);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $this->assertSame('tester@example.test', $message->getOriginalMessage()->getTo()[0]->getAddress());
        }
        $this->assertSame('log', config('mail.default'));
        $this->assertNotNull($order->fresh()->email_confirmation_sent_at);
    }
}
