<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

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
        $this->send($phone, $message);
    }

    /**
     * Notify student that they are now being served (their turn).
     */
    public function sendNowServingNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Registrar: Ticket {$ticketNumber} - It's your turn! Please proceed to the window now.";
        $this->send($phone, $message);
    }

    /**
     * Notify the next-in-line student to prepare (they are 2nd in queue).
     */
    public function sendAlmostYourTurnNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Registrar: Ticket {$ticketNumber} - You're next in line! Please prepare your requirements and stay nearby.";
        $this->send($phone, $message);
    }

    /**
     * Notify student that their transaction is completed.
     */
    public function sendCompletedNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Registrar: Ticket {$ticketNumber} - Your transaction has been completed. Thank you!";
        $this->send($phone, $message);
    }

    /**
     * Notify student that they were skipped (no response).
     */
    public function sendSkippedNotification(string $phone, string $ticketNumber): void
    {
        $message = "ACLC Registrar: Ticket {$ticketNumber} - You were skipped due to no response. Please visit the registrar's office to re-queue.";
        $this->send($phone, $message);
    }

    // ─── Core Send ────────────────────────────────────────────────────────────

    public function send(string $phone, string $message): void
    {
        // Convert PH local format 09XXXXXXXXX → E.164 +639XXXXXXXXX
        $e164 = preg_replace('/^0/', '+63', $phone);

        if (! $this->apiKey || ! $this->deviceId) {
            Log::info("[SmsService] SMS to {$e164}: {$message}");
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
        } catch (\Exception $e) {
            Log::error("[SmsService] Failed to send SMS to {$e164}: " . $e->getMessage());
        }
    }
}
