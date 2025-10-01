<?php

namespace AmplisioAIO\Tests;

use AmplisioAIO\Core\Helpers\OptionsHelper;
use PHPUnit\Framework\TestCase;

class OptionsHelperTest extends TestCase
{
    public function testMergeAndGet(): void
    {
        $store = [];

        $helper = new OptionsHelper(
            'amplisio_options',
            static function ( string $option_name, $default = [] ) use ( &$store ) {
                return $store[ $option_name ] ?? $default;
            },
            static function ( string $option_name, $value ) use ( &$store ) {
                $store[ $option_name ] = $value;

                return true;
            }
        );

        $helper->merge(
            [
                'accentColor' => '  #ABCDEF  ',
                'enabled'     => true,
            ]
        );

        self::assertSame('#ABCDEF', $helper->get('accentColor'));
        self::assertTrue($helper->get('enabled'));
    }

    public function testReplace(): void
    {
        $store = [];
        $helper = new OptionsHelper(
            'amplisio_options',
            static function ( string $option_name, $default = [] ) use ( &$store ) {
                return $store[ $option_name ] ?? $default;
            },
            static function ( string $option_name, $value ) use ( &$store ) {
                $store[ $option_name ] = $value;

                return true;
            }
        );

        $helper->replace(
            [
                'accentColor' => '#000000',
            ]
        );

        self::assertSame(['accentColor' => '#000000'], $helper->get_all());
    }
}
