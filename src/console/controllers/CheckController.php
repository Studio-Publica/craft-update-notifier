<?php

namespace Publica\CraftUpdateNotifier\console\controllers;

use craft\console\Controller;
use Publica\CraftUpdateNotifier\Plugin;
use yii\console\ExitCode;
use yii\helpers\Console;

class CheckController extends Controller
{
    /**
     * @var bool Bypass deduplication cache and always send email.
     */
    public bool $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'force',
        ]);
    }

    /**
     * Check for critical updates and notify configured recipients.
     *
     * Usage: ./craft update-notifier/check [--force]
     */
    public function actionIndex(): int
    {
        $result = Plugin::getInstance()->notifier->checkAndNotify($this->force);

        match ($result) {
            'no-recipients' => $this->stdout("No recipients configured (CRITICAL_UPDATE_NOTIFY_EMAILS is empty).\n"),
            'no-critical' => $this->stdout("No critical updates found.\n", Console::FG_GREEN),
            'already-notified' => $this->stdout("Critical updates exist but notification was already sent recently.\n", Console::FG_YELLOW),
            'sent' => $this->stdout("Critical update notification sent.\n", Console::FG_GREEN),
        };

        return ExitCode::OK;
    }
}
