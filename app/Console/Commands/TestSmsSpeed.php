<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class TestSmsSpeed extends Command
{
    protected $signature = 'sms:test-speed {phone : PH mobile number e.g. 09XXXXXXXXX} {--runs=3 : Number of test sends}';

    protected $description = 'Test Textbee SMS delivery speed — measures API response time per send';

    public function handle(): int
    {
        $phone = $this->argument('phone');
        $runs  = (int) $this->option('runs');

        // Validate phone format
        if (! preg_match('/^09[0-9]{9}$/', $phone)) {
            $this->error('Invalid phone number. Use PH format: 09XXXXXXXXX');
            return 1;
        }

        $apiKey   = config('services.textbee.key');
        $deviceId = config('services.textbee.device_id');

        if (! $apiKey || ! $deviceId) {
            $this->error('TEXTBEE_API_KEY or TEXTBEE_DEVICE_ID is not set in .env');
            return 1;
        }

        $e164   = preg_replace('/^0/', '+63', $phone);
        $client = new Client(['timeout' => 30]);

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  Textbee SMS Speed Test");
        $this->info("  Target : {$e164}");
        $this->info("  Runs   : {$runs}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->newLine();

        $durations = [];

        for ($i = 1; $i <= $runs; $i++) {
            $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $message = "ACLC Queue Test #{$i}: Your OTP is {$otp}. (Speed test — ignore this message)";

            $this->line("  Run #{$i} — Sending OTP: <comment>{$otp}</comment>");

            $start = microtime(true);

            try {
                $response = $client->post(
                    "https://api.textbee.dev/api/v1/gateway/devices/{$deviceId}/send-sms",
                    [
                        'json' => [
                            'recipients' => [$e164],
                            'message'    => $message,
                        ],
                        'headers' => [
                            'x-api-key' => $apiKey,
                        ],
                    ]
                );

                $elapsed      = round((microtime(true) - $start) * 1000); // ms
                $durations[]  = $elapsed;
                $statusCode   = $response->getStatusCode();

                $color = $elapsed < 3000 ? 'info' : ($elapsed < 8000 ? 'comment' : 'error');
                $this->{$color}("         ✓ API responded in {$elapsed}ms (HTTP {$statusCode})");

            } catch (\Exception $e) {
                $elapsed     = round((microtime(true) - $start) * 1000);
                $durations[] = $elapsed;
                $this->error("         ✗ Failed after {$elapsed}ms — " . $e->getMessage());
            }

            // Wait 3 seconds between runs to avoid spam
            if ($i < $runs) {
                $this->line("         ⏳ Waiting 3s before next run...");
                sleep(3);
            }
        }

        // Summary
        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  Results Summary");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $min = min($durations);
        $max = max($durations);
        $avg = round(array_sum($durations) / count($durations));

        $this->line("  Fastest  : <info>{$min}ms</info>");
        $this->line("  Slowest  : <comment>{$max}ms</comment>");
        $this->line("  Average  : <comment>{$avg}ms</comment>");
        $this->newLine();
        $this->line("  <fg=gray>Note: This measures API response time only (server → Textbee).</>");
        $this->line("  <fg=gray>Actual SMS delivery to phone may take additional seconds</>");
        $this->line("  <fg=gray>depending on your Android phone signal and background state.</>");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return 0;
    }
}
