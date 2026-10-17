<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use PDO;
use PDOException;
use RuntimeException;

class WebsiteAnalyticsService
{
    private function path(): string
    {
        return (string) config('pharmacy.marketing_analytics_db_path');
    }

    private function connect(bool $readonly): ?PDO
    {
        $path = $this->path();

        // Never create a second empty metrics database when marketing analytics are offline.
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            return null;
        }

        $database = new PDO(
            $readonly ? 'sqlite:file:'.$realPath.'?mode=ro' : 'sqlite:'.$realPath,
            null,
            null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 4, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $database->exec('PRAGMA busy_timeout=4000');

        return $database;
    }

    public function snapshot(): array
    {
        $empty = [
            'available' => false,
            'metrics' => ['views' => 0, 'ai_referrals' => 0, 'demo_requests' => 0, 'contact_requests' => 0],
            'sources' => [], 'ai_providers' => [], 'pages' => [], 'campaigns' => [],
            'mentions' => [], 'mention_totals' => [],
        ];

        try {
            $db = $this->connect(true);
            if ($db === null) {
                return $empty;
            }

            $since = gmdate('Y-m-d', time() - (29 * 86400));

            $group = static function (PDO $db, string $column, string $filter, string $since): array {
                $allowed = ['event', 'source', 'referrer', 'path', 'campaign'];
                if (! in_array($column, $allowed, true)) {
                    return [];
                }

                $stmt = $db->prepare("SELECT {$column} AS label, SUM(count) AS total FROM daily_metrics
                    WHERE day >= :since {$filter} GROUP BY {$column} ORDER BY total DESC LIMIT 15");
                $stmt->execute(['since' => $since]);

                return array_map(
                    static fn (array $row): array => ['label' => (string) $row['label'], 'total' => (int) $row['total']],
                    $stmt->fetchAll(),
                );
            };

            $events = [];
            foreach ($group($db, 'event', '', $since) as $event) {
                $events[$event['label']] = $event['total'];
            }

            $aiProviders = $group($db, 'referrer', "AND event='page_view' AND source='ai_referral'", $since);
            $mentions = [];
            $mentionTotals = [];
            $exists = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='ai_mention_checks'")->fetchColumn();
            if ($exists) {
                $mentions = $db->query('SELECT checked_at, engine, question, result, evidence_url FROM ai_mention_checks ORDER BY id DESC LIMIT 20')->fetchAll();
                foreach ($db->query('SELECT result, COUNT(*) AS total FROM ai_mention_checks GROUP BY result')->fetchAll() as $row) {
                    $mentionTotals[$row['result']] = (int) $row['total'];
                }
            }

            return [
                'available' => true,
                'metrics' => [
                    'views' => $events['page_view'] ?? 0,
                    'ai_referrals' => array_sum(array_column($aiProviders, 'total')),
                    'demo_requests' => $events['demo_received'] ?? 0,
                    'contact_requests' => $events['contact_received'] ?? 0,
                ],
                'sources' => $group($db, 'source', "AND event='page_view'", $since),
                'ai_providers' => $aiProviders,
                'pages' => $group($db, 'path', "AND event='page_view'", $since),
                'campaigns' => $group($db, 'campaign', "AND event='page_view' AND campaign != ''", $since),
                'mentions' => $mentions,
                'mention_totals' => $mentionTotals,
            ];
        } catch (PDOException $e) {
            report($e);

            return $empty;
        }
    }

    public function recordMention(string $engine, string $question, string $result, string $evidenceUrl = ''): void
    {
        $db = $this->connect(false);
        if ($db === null) {
            throw new RuntimeException('Website analytics storage is unavailable.');
        }

        $db->exec('CREATE TABLE IF NOT EXISTS ai_mention_checks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            checked_at TEXT NOT NULL,
            engine TEXT NOT NULL,
            question TEXT NOT NULL,
            result TEXT NOT NULL,
            evidence_url TEXT NOT NULL DEFAULT ""
        )');

        $stmt = $db->prepare('INSERT INTO ai_mention_checks (checked_at, engine, question, result, evidence_url) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([gmdate('Y-m-d H:i:s'), $engine, $question, $result, $evidenceUrl]);
    }
}
