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
            'metrics' => ['views' => 0, 'ai_referrals' => 0, 'demo_requests' => 0, 'contact_requests' => 0, 'unique_visitors' => 0, 'sessions' => 0, 'engaged_sessions' => 0, 'bounce_rate' => null],
            'sources' => [], 'ai_providers' => [], 'pages' => [], 'campaigns' => [],
            'mentions' => [], 'mention_totals' => [], 'countries' => [],
            'daily' => [], 'landing_pages' => [], 'campaign_terms' => [],
            'search_queries' => [], 'search_period' => null,
            'coverage' => ['visitors' => false, 'countries' => false, 'sessions' => false],
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

            $tableExists = static fn (string $table): bool => (bool) $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='".str_replace("'", '', $table)."'")->fetchColumn();
            $hasSessions = $tableExists('visit_sessions');
            $hasCountry = $tableExists('country_metrics');
            $hasVisitors = $tableExists('visitor_days');
            $sessionStats = ['unique_visitors' => 0, 'sessions' => 0, 'engaged_sessions' => 0, 'bounce_rate' => null];
            $landing = [];
            if ($hasSessions) {
                $stmt = $db->prepare('SELECT COUNT(*) AS sessions, COUNT(DISTINCT visitor_hash) AS unique_visitors,
                    COALESCE(SUM(engaged),0) AS engaged_sessions,
                    SUM(CASE WHEN pageviews=1 AND engaged=0 THEN 1 ELSE 0 END) AS bounces
                    FROM visit_sessions WHERE day >= ?');
                $stmt->execute([$since]);
                $stats = $stmt->fetch();
                $sessionStats = ['unique_visitors' => (int) $stats['unique_visitors'],
                    'sessions' => (int) $stats['sessions'], 'engaged_sessions' => (int) $stats['engaged_sessions'],
                    'bounce_rate' => (int) $stats['sessions'] ? round(100 * (int) $stats['bounces'] / (int) $stats['sessions'], 1) : null];
                $landingStmt = $db->prepare('SELECT landing_path AS label, COUNT(*) AS total FROM visit_sessions
                    WHERE day >= ? GROUP BY landing_path ORDER BY total DESC LIMIT 15');
                $landingStmt->execute([$since]);
                $landing = $landingStmt->fetchAll();
            }
            $countries = [];
            if ($hasCountry) {
                $stmt = $db->prepare("SELECT country AS label,SUM(count) AS total FROM country_metrics
                    WHERE day>=? AND event='page_view' GROUP BY country ORDER BY total DESC LIMIT 30");
                $stmt->execute([$since]);
                $countries = $stmt->fetchAll();
            }
            $dailyStmt = $db->prepare("SELECT day AS label, SUM(count) AS total FROM daily_metrics
                WHERE day >= ? AND event='page_view' GROUP BY day ORDER BY day ASC");
            $dailyStmt->execute([$since]);
            $daily = $dailyStmt->fetchAll();
            $terms = [];
            if ($tableExists('campaign_terms')) {
                $stmt = $db->prepare('SELECT term AS label, SUM(count) AS total FROM campaign_terms
                    WHERE day >= ? GROUP BY term ORDER BY total DESC LIMIT 30');
                $stmt->execute([$since]);
                $terms = $stmt->fetchAll();
            }
            $searchQueries = [];
            $searchPeriod = null;
            if ($tableExists('search_console_queries')) {
                $searchPeriod = $db->query('SELECT period_start,period_end,imported_at FROM search_console_queries
                    ORDER BY imported_at DESC LIMIT 1')->fetch() ?: null;
                if ($searchPeriod) {
                    $stmt = $db->prepare('SELECT query, clicks, impressions, ctr, position FROM search_console_queries
                        WHERE period_start=? AND period_end=? ORDER BY impressions DESC LIMIT 70');
                    $stmt->execute([$searchPeriod['period_start'], $searchPeriod['period_end']]);
                    $searchQueries = $stmt->fetchAll();
                }
            }

            return [
                'available' => true,
                'metrics' => [
                    'views' => $events['page_view'] ?? 0,
                    'ai_referrals' => array_sum(array_column($aiProviders, 'total')),
                    'demo_requests' => $events['demo_received'] ?? 0,
                    'contact_requests' => $events['contact_received'] ?? 0,
                    ...$sessionStats,
                ],
                'sources' => $group($db, 'source', "AND event='page_view'", $since),
                'ai_providers' => $aiProviders,
                'pages' => $group($db, 'path', "AND event='page_view'", $since),
                'campaigns' => $group($db, 'campaign', "AND event='page_view' AND campaign != ''", $since),
                'mentions' => $mentions,
                'mention_totals' => $mentionTotals,
                'countries' => $countries, 'daily' => $daily,
                'landing_pages' => $landing, 'campaign_terms' => $terms,
                'search_queries' => $searchQueries, 'search_period' => $searchPeriod,
                'coverage' => ['visitors' => $hasVisitors, 'countries' => $hasCountry, 'sessions' => $hasSessions],
            ];
        } catch (PDOException $e) {
            report($e);

            return $empty;
        }
    }

    /** Import the owner's verified Google Search Console Queries CSV; not inferred search keywords. */
    public function importSearchQueries(string $csvFile, string $from, string $to): int
    {
        $db = $this->connect(false);
        if ($db === null) {
            throw new RuntimeException('Website analytics storage unavailable.');
        }
        $handle = fopen($csvFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('CSV could not be read.');
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('CSV is empty.');
        }
        $names = array_map(static fn ($name) => strtolower(trim((string) $name, " \t
\r\x00\x0B\xEF\xBB\xBF")), $header);
        $index = [];
        foreach (['query' => ['top queries', 'query', 'queries'], 'clicks' => ['clicks'], 'impressions' => ['impressions'],
            'ctr' => ['ctr'], 'position' => ['position']] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $at = array_search($alias, $names, true);
                if ($at !== false) {
                    $index[$key] = $at;
                    break;
                }
            }
        }
        if (count($index) !== 5) {
            fclose($handle);
            throw new RuntimeException('Expected Search Console columns: Top queries, Clicks, Impressions, CTR, Position.');
        }
        $rows = [];
        while (($record = fgetcsv($handle)) !== false) {
            if (count($rows) >= 3000) {
                fclose($handle);
                throw new RuntimeException('CSV exceeds 3,000 queries.');
            }
            $query = trim((string) ($record[$index['query']] ?? ''));
            if ($query === '' || mb_strlen($query) > 250 || str_starts_with($query, '=')) {
                continue;
            }
            $clicks = $record[$index['clicks']] ?? null;
            $impressions = $record[$index['impressions']] ?? null;
            $ctr = str_replace('%', '', (string) ($record[$index['ctr']] ?? ''));
            $position = $record[$index['position']] ?? null;
            if (! is_numeric($clicks) || ! is_numeric($impressions) || ! is_numeric($ctr) || ! is_numeric($position)) {
                continue;
            }
            $clicks = (int) $clicks;
            $impressions = (int) $impressions;
            $ctr = (float) $ctr;
            $position = (float) $position;
            if ($clicks < 0 || $impressions < 0 || $clicks > $impressions || $ctr < 0 || $ctr > 100 || $position < 0 || $position > 1000) {
                continue;
            }
            $rows[] = [$from, $to, $query, $clicks, $impressions, $ctr, $position, gmdate('Y-m-d H:i:s')];
        }
        fclose($handle);
        if (! $rows) {
            throw new RuntimeException('No valid Google Search Console query rows found.');
        }
        $db->exec('CREATE TABLE IF NOT EXISTS search_console_queries (
            period_start TEXT NOT NULL, period_end TEXT NOT NULL, query TEXT NOT NULL,
            clicks INTEGER NOT NULL, impressions INTEGER NOT NULL,
            ctr REAL NOT NULL, position REAL NOT NULL, imported_at TEXT NOT NULL,
            PRIMARY KEY(period_start,period_end,query)
        )');
        $db->beginTransaction();
        try {
            $delete = $db->prepare('DELETE FROM search_console_queries WHERE period_start=? AND period_end=?');
            $delete->execute([$from, $to]);
            $stmt = $db->prepare('INSERT INTO search_console_queries VALUES(?,?,?,?,?,?,?,?)');
            foreach ($rows as $row) {
                $stmt->execute($row);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return count($rows);
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
