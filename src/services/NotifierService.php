<?php

namespace Publica\CraftUpdateNotifier\services;

use Craft;
use craft\helpers\App;
use yii\base\Component;

class NotifierService extends Component
{
    private const CACHE_KEY = 'update-notifier:fingerprint';
    private const CACHE_TTL = 432000; // 5 days

    /**
     * Check for critical updates and send email notification.
     *
     * @return string Result status: no-recipients, no-critical, already-notified, sent
     */
    public function checkAndNotify(bool $force = false): string
    {
        $recipients = $this->getRecipients();

        if (empty($recipients)) {
            return 'no-recipients';
        }

        $updates = Craft::$app->getUpdates()->getUpdates(true);

        if (!$updates->getHasCritical()) {
            return 'no-critical';
        }

        $criticalPackages = $this->collectCriticalPackages($updates);
        $fingerprint = sha1(json_encode($criticalPackages));

        if (!$force && Craft::$app->getCache()->get(self::CACHE_KEY) === $fingerprint) {
            return 'already-notified';
        }

        $this->sendNotification($recipients, $criticalPackages);

        Craft::$app->getCache()->set(self::CACHE_KEY, $fingerprint, self::CACHE_TTL);

        return 'sent';
    }

    private function getRecipients(): array
    {
        $raw = App::env('CRITICAL_UPDATE_NOTIFY_EMAILS') ?: '';

        return array_filter(array_map('trim', explode(',', $raw)));
    }

    private function collectCriticalPackages($updates): array
    {
        $packages = [];

        if ($updates->cms->getHasCritical()) {
            foreach ($updates->cms->releases as $release) {
                if ($release->critical) {
                    $packages[] = [
                        'name' => 'craftcms/cms',
                        'version' => $release->version,
                        'notes' => strip_tags($release->notes ?? ''),
                    ];
                    break;
                }
            }
        }

        foreach ($updates->plugins as $handle => $pluginUpdate) {
            if ($pluginUpdate->getHasCritical()) {
                foreach ($pluginUpdate->releases as $release) {
                    if ($release->critical) {
                        $packages[] = [
                            'name' => $handle,
                            'version' => $release->version,
                            'notes' => strip_tags($release->notes ?? ''),
                        ];
                        break;
                    }
                }
            }
        }

        return $packages;
    }

    private function sendNotification(array $recipients, array $packages): void
    {
        $siteName = Craft::$app->getSystemName();
        $siteUrl = Craft::$app->getSites()->getPrimarySite()->baseUrl;
        $environment = App::env('CRAFT_ENVIRONMENT') ?: 'unknown';
        $subject = "[{$siteName} / {$environment}] Craft CMS - " . $this->buildSubjectSummary($packages);

        Craft::$app->getMailer()
            ->compose()
            ->setTo($recipients)
            ->setSubject($subject)
            ->setHtmlBody($this->buildHtmlBody($packages, $environment, $siteName, $siteUrl))
            ->setTextBody($this->buildTextBody($packages, $environment, $siteName, $siteUrl))
            ->send();
    }

    private function buildSubjectSummary(array $packages): string
    {
        $cmsPackage = null;
        $pluginNames = [];

        foreach ($packages as $pkg) {
            if ($pkg['name'] === 'craftcms/cms') {
                $cmsPackage = $pkg;
            } else {
                $pluginNames[] = $pkg['name'];
            }
        }

        $parts = [];

        if ($cmsPackage) {
            $parts[] = "Critical CMS update available ({$cmsPackage['version']})";
        }

        if (count($pluginNames) > 0) {
            $count = count($pluginNames);
            $names = implode(', ', $pluginNames);
            $parts[] = "{$count} critical plugin " . ($count === 1 ? 'update' : 'updates') . " available ({$names})";
        }

        return implode(' + ', $parts);
    }

    private function buildHtmlBody(array $packages, string $environment, string $siteName, string $siteUrl): string
    {
        $rows = '';
        foreach ($packages as $pkg) {
            $name = htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8');
            $version = htmlspecialchars($pkg['version'], ENT_QUOTES, 'UTF-8');
            $notes = htmlspecialchars($pkg['notes'], ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td style=\"padding:8px;border:1px solid #ddd;\">{$name}</td>"
                . "<td style=\"padding:8px;border:1px solid #ddd;\">{$version}</td>"
                . "<td style=\"padding:8px;border:1px solid #ddd;\">{$notes}</td></tr>";
        }

        return <<<HTML
        <div style="font-family:sans-serif;max-width:600px;">
            <h2 style="color:#c0392b;">Critical Updates Available ({$environment})</h2>
            <p>The following packages have critical updates that should be applied promptly:</p>
            <table style="border-collapse:collapse;width:100%;margin:16px 0;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="padding:8px;border:1px solid #ddd;text-align:left;">Package</th>
                        <th style="padding:8px;border:1px solid #ddd;text-align:left;">Version</th>
                        <th style="padding:8px;border:1px solid #ddd;text-align:left;">Notes</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            <p style="color:#999;font-size:12px;">{$siteName} &mdash; <a href="{$siteUrl}" style="color:#999;">{$siteUrl}</a></p>
            <p style="color:#999;font-size:12px;">This is an automated notification from the Publica Craft Update Notifier plugin.</p>
        </div>
        HTML;
    }

    private function buildTextBody(array $packages, string $environment, string $siteName, string $siteUrl): string
    {
        $lines = ["Critical Updates Available ({$environment})", str_repeat('=', 50), ''];

        foreach ($packages as $pkg) {
            $lines[] = "- {$pkg['name']} → {$pkg['version']}";
            if (!empty($pkg['notes'])) {
                $lines[] = "  Notes: {$pkg['notes']}";
            }
        }

        $lines[] = '';
        $lines[] = "{$siteName} — {$siteUrl}";
        $lines[] = '-- This is an automated notification from the Publica Craft Update Notifier plugin.';

        return implode("\n", $lines);
    }
}
