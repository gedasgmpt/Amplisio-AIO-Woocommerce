<?php

namespace AmplisioAIO\Core\Helpers;

class ThemeTokenHelper
{
    public function tokens(): array
    {
        $active_theme = wp_get_theme();

        return [
            'name'        => $active_theme->get( 'Name' ),
            'version'     => $active_theme->get( 'Version' ),
            'stylesheet'  => $active_theme->get_stylesheet(),
            'template'    => $active_theme->get_template(),
            'supportsRTL' => $active_theme->get( 'TextDomain' ) && is_rtl(),
        ];
    }
}
