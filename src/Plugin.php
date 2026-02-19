<?php

namespace Publica\CraftUpdateNotifier;

use Craft;
use Publica\CraftUpdateNotifier\services\NotifierService;

class Plugin extends \craft\base\Plugin
{
    public bool $hasCpSection = false;

    public static function config(): array
    {
        return [
            'components' => [
                'notifier' => NotifierService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'Publica\\CraftUpdateNotifier\\console\\controllers';
        }
    }
}
