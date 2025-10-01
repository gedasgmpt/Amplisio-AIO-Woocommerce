<?php

namespace AmplisioAIO\Core;

use AmplisioAIO\Core\Helpers\AssetHelper;
use AmplisioAIO\Core\Helpers\OptionsHelper;

class FrontAssets
{
    private AssetHelper $assets;

    private OptionsHelper $options;

    public function __construct( AssetHelper $assets, OptionsHelper $options )
    {
        $this->assets  = $assets;
        $this->options = $options;
    }

    public function hooks(): void
    {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void
    {
        if ( function_exists( 'is_woocommerce' ) ) {
            $should_enqueue = is_woocommerce();

            if ( function_exists( 'is_cart' ) ) {
                $should_enqueue = $should_enqueue || is_cart();
            }

            if ( function_exists( 'is_checkout' ) ) {
                $should_enqueue = $should_enqueue || is_checkout();
            }

            if ( ! $should_enqueue ) {
                return;
            }
        }

        $this->assets->enqueue( 'front', 'amplisio-aio-front', [], true );

        $data = [
            'accentColor' => $this->options->get( 'accentColor', '#2d6cdf' ),
        ];

        wp_add_inline_script(
            'amplisio-aio-front',
            'window.amplisioAioFront = ' . wp_json_encode( $data ) . ';',
            'before'
        );
    }
}
