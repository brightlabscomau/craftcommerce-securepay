<?php

namespace brightlabs\securepay\assets;

use craft\web\AssetBundle;

/**
 * SecurePay Asset Bundle
 *
 * Asset bundle for SecurePay payment form styles
 * @since 1.4.1
 */
class SecurePayAsset extends AssetBundle
{
    /**
     * @inheritdoc
     * @since 1.4.1
     */
    public function init(): void
    {
        $this->sourcePath = '@brightlabs/securepay/assets';

        $this->css = [
            'css/securepay.css',
        ];

        $this->js = [
            'js/securepay.js',
        ];

        parent::init();
    }
}