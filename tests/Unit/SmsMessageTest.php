<?php

namespace Tests\Unit;

use App\Services\SmsService;
use Tests\TestCase;

class SmsMessageTest extends TestCase
{
    public function test_now_serving_message_names_the_window(): void
    {
        $sms = new SmsService();

        $this->assertSame(
            "ACLC Cashier: Ticket 003 - It's your turn! Please proceed to Window 2 now.",
            $sms->nowServingMessage('003', 'Cashier', 'Window 2')
        );
    }

    public function test_now_serving_message_falls_back_to_generic_text_without_a_window(): void
    {
        $sms = new SmsService();

        $this->assertSame(
            "ACLC Cashier: Ticket 003 - It's your turn! Please proceed to the window now.",
            $sms->nowServingMessage('003', 'Cashier')
        );
    }
}
