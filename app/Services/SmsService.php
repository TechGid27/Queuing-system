<?php

namespace App\Services;

use App\Models\SmsNotification;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SmsService
{
    private ?string $apiKey;
    private ?string $deviceId;
    private Client $client;

    public function __construct()
    {
        $this->apiKey   = config('services.textbee.key');
        $this->deviceId = config('services.textbee.device_id');
        $this->client   = new Client(['timeout' => 15]);
    }

    // ─── Public Methods ───────────────────────────────────────────────────────

    /**
     * Send OTP verification code during registration.
     */
    public function sendOtp(string $phone, string $otp): void
    {
        $message = "Your ACLC Queue System verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        $this->send($phone, $message, 'otp');
    }

    /**
     * Send OTP for password reset.
     */
    public function sendPasswordResetOtp(string $phone, string $otp): void
    {
        $message = "ACLC Queue System: Your password reset code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        $this->send($phone, $message, 'password_reset');
    }

    /**
     * Notify student that they are now being served (their turn).
     */
    public function sendNowServingNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Cashier: Ticket {$ticketNumber} - It's your turn! Please proceed to the window now.";
        $this->send($phone, $message, 'now_serving');
    }

    /**
     * Notify the next-in-line student to prepare (they are 2nd in queue).
     */
    public function sendAlmostYourTurnNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Cashier: Ticket {$ticketNumber} - You're next in line! Please prepare your requirements and stay nearby.";
        $this->send($phone, $message, 'almost_turn');
    }

    /**
     * Notify student that their transaction is completed.
     */
    public function sendCompletedNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Cashier: Ticket {$ticketNumber} - Your transaction has been completed. Thank you!";
        $this->send($phone, $message, 'completed');
    }

    /**
     * Notify student that they were skipped (no response).
     */
    public function sendSkippedNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Cashier: Ticket {$ticketNumber} - You were skipped due to no response. Please visit the Cashier's office to re-queue.";
        $this->send($phone, $message, 'skipped');
    }

    // ─── Core Send ────────────────────────────────────────────────────────────

    public function send(string $phone, string $message, string $type = 'notification'): void
    {
        // Convert PH local format 09XXXXXXXXX → E.164 +639XXXXXXXXX
        $e164 = preg_replace('/^0/', '+63', $phone);
        $delivery = $this->createDelivery($e164, $type);

        if (! $this->apiKey || ! $this->deviceId) {
            $delivery?->update([
                'status' => 'fallback',
                'sent_at' => now(),
            ]);
            Log::info('[SmsService] SMS recorded using fallback mode.', [
                'to' => $e164,
                'type' => $type,
            ]);
            return;
        }

        try {
            $this->client->post(
                "https://api.textbee.dev/api/v1/gateway/devices/{$this->deviceId}/send-sms",
                [
                    'json' => [
                        'recipients' => [$e164],
                        'message'    => $message,
                    ],
                    'headers' => [
                        'x-api-key' => $this->apiKey,
                    ],
                ]
            );
            $delivery?->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $delivery?->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 1000),
            ]);
            Log::error('[SmsService] Failed to send SMS.', [
                'to' => $e164,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createDelivery(string $phone, string $type): ?SmsNotification
    {
        try {
            return SmsNotification::create([
                'phone_number' => $phone,
                'type' => $type,
                'status' => 'pending',
            ]);
        } catch (Throwable $exception) {
            Log::warning('[SmsService] Unable to record SMS delivery.', [
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
