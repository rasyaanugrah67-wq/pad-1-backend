<?php

namespace Tests\Unit;

use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppNotificationServiceTest extends TestCase
{
    private function configureFonnte(array $overrides = []): void
    {
        Config::set('services.whatsapp', array_merge([
            'enabled' => true,
            'provider' => 'fonnte',
            'api_url' => 'https://api.fonnte.com/send',
            'token' => 'fake-fonnte-token-xyz',
            'timeout' => 15,
            'test_mode' => false,
            'test_number' => null,
        ], $overrides));
    }

    public function test_it_sends_normalized_number_to_fonnte_with_raw_token_and_form_payload(): void
    {
        $this->configureFonnte();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
                'id' => ['123456'],
                'target' => ['6281234567890'],
            ], 200),
        ]);

        $service = app(WhatsAppNotificationService::class);
        $result = $service->sendWithResult('0812-3456-7890', 'Halo warga RW 01');

        $this->assertTrue($result['success']);
        $this->assertSame(WhatsAppNotificationService::STATE_QUEUED, $result['state']);
        $this->assertSame('pending', $result['provider_process']);
        $this->assertSame('success! message in queue', $result['provider_detail']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->method() === 'POST'
                && $request->header('Authorization') === ['fake-fonnte-token-xyz']
                && str_contains(implode(';', $request->header('Content-Type')), 'application/x-www-form-urlencoded')
                && $request['target'] === '6281234567890'
                && $request['message'] === 'Halo warga RW 01';
        });
    }

    public function test_it_normalizes_various_indonesian_phone_formats(): void
    {
        $service = app(WhatsAppNotificationService::class);

        $this->assertSame('6281234567890', $service->normalizePhoneNumber('0812-3456-7890'));
        $this->assertSame('6281234567890', $service->normalizePhoneNumber('+62 812 3456 7890'));
        $this->assertSame('6281234567890', $service->normalizePhoneNumber('62081234567890'));
        $this->assertSame('6281234567890', $service->normalizePhoneNumber('81234567890'));
        $this->assertSame('', $service->normalizePhoneNumber('   '));
    }

    public function test_test_mode_redirects_all_notifications_to_test_number_preserving_message_content(): void
    {
        $this->configureFonnte([
            'test_mode' => true,
            'test_number' => '0899-1111-2222',
        ]);
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        $service = app(WhatsAppNotificationService::class);
        $sent = $service->sendIuranNotification('081200009999', 'Budi Santoso', 50000, '2026-08-10', 'Iuran HUT RI');

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return $request['target'] === '6289911112222'
                && str_contains($request['message'], 'Budi Santoso')
                && str_contains($request['message'], 'Rp50.000')
                && str_contains($request['message'], 'Iuran HUT RI');
        });
    }

    public function test_test_mode_without_test_number_aborts_safely_without_fallback(): void
    {
        $this->configureFonnte([
            'test_mode' => true,
            'test_number' => '',
        ]);
        Http::fake();

        $service = app(WhatsAppNotificationService::class);
        $result = $service->sendWithResult('081234567890', 'Jangan terkirim ke warga');

        $this->assertFalse($result['success']);
        $this->assertSame(WhatsAppNotificationService::STATE_FAILED, $result['state']);
        $this->assertSame('missing_test_number', $result['reason']);
        Http::assertNothingSent();
    }

    public function test_it_does_not_make_a_request_when_disabled(): void
    {
        $this->configureFonnte([
            'enabled' => false,
        ]);
        Http::fake();

        $service = app(WhatsAppNotificationService::class);
        $result = $service->sendWithResult('081234567890', 'Halo warga');

        $this->assertFalse($result['success']);
        $this->assertSame(WhatsAppNotificationService::STATE_SKIPPED, $result['state']);
        Http::assertNothingSent();
    }

    public function test_it_does_not_make_a_request_when_token_is_missing(): void
    {
        $this->configureFonnte([
            'token' => '',
        ]);
        Http::fake();

        $service = app(WhatsAppNotificationService::class);
        $result = $service->sendWithResult('081234567890', 'Halo warga');

        $this->assertFalse($result['success']);
        $this->assertSame('incomplete_configuration', $result['reason']);
        Http::assertNothingSent();
    }

    public function test_it_handles_fonnte_http_error_and_logical_false_status_safely(): void
    {
        $this->configureFonnte();
        Http::fake([
            'https://api.fonnte.com/send' => Http::sequence()
                ->push(['status' => false, 'reason' => 'invalid token'], 200)
                ->push(['status' => false, 'reason' => 'unauthorized'], 401),
        ]);

        $service = app(WhatsAppNotificationService::class);

        $this->assertFalse($service->send('081234567890', 'Pesan 1'));
        $this->assertFalse($service->send('081234567890', 'Pesan 2'));
    }

    public function test_it_handles_connection_exception_safely(): void
    {
        $this->configureFonnte();
        Http::fake(function () {
            throw new ConnectionException('Timeout connecting to Fonnte');
        });

        $service = app(WhatsAppNotificationService::class);
        $result = $service->sendWithResult('081234567890', 'Halo warga');

        $this->assertFalse($result['success']);
        $this->assertSame(WhatsAppNotificationService::STATE_FAILED, $result['state']);
        $this->assertSame('exception', $result['reason']);
    }

    public function test_token_and_message_content_never_appear_in_logs(): void
    {
        $secretToken = 'super-secret-fonnte-token-999';
        $sensitiveMessage = 'Isi pesan rahasia warga 12345';

        $this->configureFonnte(['token' => $secretToken]);
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => false,
                'reason' => "invalid token {$secretToken}",
            ], 401),
        ]);

        $loggedEntries = [];
        Log::shouldReceive('error')
            ->once()
            ->andReturnUsing(function (string $message, array $context = []) use (&$loggedEntries) {
                $loggedEntries[] = json_encode([$message, $context]);
            });

        $service = app(WhatsAppNotificationService::class);
        $this->assertFalse($service->send('081234567890', $sensitiveMessage));

        $serializedLogs = implode("\n", $loggedEntries);
        $this->assertStringNotContainsString($secretToken, $serializedLogs);
        $this->assertStringNotContainsString($sensitiveMessage, $serializedLogs);
    }

    public function test_send_account_credential_renders_username_and_initial_password_without_logging_password(): void
    {
        $this->configureFonnte();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        $loggedEntries = [];
        Log::shouldReceive('info')
            ->once()
            ->andReturnUsing(function (string $message, array $context = []) use (&$loggedEntries) {
                $loggedEntries[] = json_encode([$message, $context]);
            });

        $service = app(WhatsAppNotificationService::class);
        $sent = $service->sendAccountCredential('081234567890', 'Budi Santoso', 'BudiSantoso', 'RahasiaAwal123!');

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return $request['target'] === '6281234567890'
                && str_contains($request['message'], 'Budi Santoso')
                && str_contains($request['message'], 'BudiSantoso')
                && str_contains($request['message'], 'RahasiaAwal123!')
                && str_contains($request['message'], 'menu Profil');
        });

        $this->assertStringNotContainsString('RahasiaAwal123!', implode("\n", $loggedEntries));
    }

    public function test_new_pad_notification_helpers_send_expected_messages(): void
    {
        $this->configureFonnte([
            'test_mode' => true,
            'test_number' => '6289900001111',
        ]);
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        $service = app(WhatsAppNotificationService::class);

        $this->assertTrue($service->sendPaymentConfirmation('081234567890', 'Siti', 75000, 'Iuran Agustus', '2026-08-05 10:00'));
        $this->assertTrue($service->sendLombaParticipantReminder('081234567890', 'Siti', 'Tarik Tambang', '17 Agu 2026 08:00', 'Lapangan RW 01'));
        $this->assertTrue($service->sendMatchWatchInvitation('081234567890', 'Siti', 'Futsal', '17 Agu 2026 15:30', 'Lapangan RW 01', 'Final'));

        Http::assertSentCount(3);
        Http::assertSent(fn ($r) => $r['target'] === '6289900001111' && str_contains($r['message'], 'Sudah Bayar'));
        Http::assertSent(fn ($r) => $r['target'] === '6289900001111' && str_contains($r['message'], 'Tarik Tambang'));
        Http::assertSent(fn ($r) => $r['target'] === '6289900001111' && str_contains($r['message'], 'Final'));
    }
}
