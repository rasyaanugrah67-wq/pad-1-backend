<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    public function send(
        string $phoneNumber,
        string $message
    ): bool {

        $phoneNumber = $this->normalizePhoneNumber(
            $phoneNumber
        );

        /*
         * Untuk development sementara hanya log.
         */

        Log::info('Mock WhatsApp Notification', [
            'phone_number' => $phoneNumber,
            'message' => $message,
        ]);

        return true;
    }

    public function sendAccountCredential(
        string $phoneNumber,
        string $name,
        string $username,
        string $password
    ): bool {

        $message = <<<MESSAGE
Halo {$name},

Akun Website Perlombaan 17 Agustus Anda telah dibuat.

Username: {$username}
Password: {$password}

Silakan login dan segera ganti password Anda.

Terima kasih.
MESSAGE;

        return $this->send(
            $phoneNumber,
            $message
        );
    }

    public function sendIuranNotification(
        string $phoneNumber,
        string $name,
        float $nominal,
        string $dueDate
    ): bool {

        $formattedNominal = number_format(
            $nominal,
            0,
            ',',
            '.'
        );

        $message = <<<MESSAGE
Halo {$name},

Tagihan iuran kegiatan 17 Agustus telah dibuat.

Nominal: Rp{$formattedNominal}
Jatuh tempo: {$dueDate}

Silakan melakukan pembayaran sebelum tanggal jatuh tempo.

Terima kasih.
MESSAGE;

        return $this->send(
            $phoneNumber,
            $message
        );
    }

    private function normalizePhoneNumber(
        string $phoneNumber
    ): string {

        $phoneNumber = preg_replace(
            '/[^0-9]/',
            '',
            $phoneNumber
        );

        if (str_starts_with($phoneNumber, '0')) {
            return '62' . substr(
                $phoneNumber,
                1
            );
        }

        return $phoneNumber;
    }
}