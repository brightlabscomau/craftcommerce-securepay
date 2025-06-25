<?php

namespace brightlabs\securepay;

use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use brightlabs\securepay\gateways\Gateway;
use yii\base\Event;

/**
 * Plugin represents the SecurePay plugin.
 *
 * @author Brightlabs
 * @since 1.0
 */
class Plugin extends \craft\base\Plugin
{
    /**
     * @inheritdoc
     */
    public string $schemaVersion = '1.3.0';

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = false;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        Event::on(
           Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
            }
        );
    }

} 