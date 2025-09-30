<?php

use Amplisio\AIO\Modules\AbandonedCart\AbandonedCartSequenceRepository;

class AbandonedCartSequenceRepositoryTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option('amplisio_aio_abandoned_sequences');
    }

    public function test_get_settings_returns_defaults_when_empty(): void
    {
        $repository = new AbandonedCartSequenceRepository();

        $settings = $repository->get_settings();

        $this->assertSame(60, $settings['abandonAfterMinutes']);
        $this->assertSame(14, $settings['expireAfterDays']);
        $this->assertCount(2, $settings['sequences']);
    }

    public function test_update_settings_sanitizes_sequences(): void
    {
        $repository = new AbandonedCartSequenceRepository();

        $repository->update_settings([
            'abandonAfterMinutes' => 2,
            'expireAfterDays'     => 0,
            'sequences'           => [
                [
                    'id'             => '  Reminder One ',
                    'name'           => '<strong>Reminder</strong> #1',
                    'delay'          => 1,
                    'subject'        => '<span>Subject</span>',
                    'body'           => '<script>alert(1)</script><p><strong>Hi</strong></p>',
                    'autoCoupon'     => '1',
                    'couponType'     => 'bogus',
                    'couponAmount'   => '15.5',
                    'couponExpiryDays' => 0,
                ],
                [
                    'id'   => '',
                    'name' => 'Missing id',
                ],
            ],
        ]);

        $settings = $repository->get_settings();

        $this->assertSame(5, $settings['abandonAfterMinutes']);
        $this->assertSame(1, $settings['expireAfterDays']);
        $this->assertCount(1, $settings['sequences']);

        $sequence = $settings['sequences'][0];

        $this->assertSame('reminder-one', $sequence['id']);
        $this->assertSame('Reminder #1', $sequence['name']);
        $this->assertSame(5, $sequence['delay']);
        $this->assertSame('Subject', $sequence['subject']);
        $this->assertSame('<p><strong>Hi</strong></p>', $sequence['body']);
        $this->assertTrue($sequence['autoCoupon']);
        $this->assertSame('percent', $sequence['couponType']);
        $this->assertSame(15.5, $sequence['couponAmount']);
        $this->assertSame(1, $sequence['couponExpiryDays']);
    }
}
