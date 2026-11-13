<?php

namespace App\Modules\Settlement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CafMatchReport
{
    public const EVENT = 'afcon-2027-md3-cod-sle-2026-11-12';

    public const CONDITION = 'regulation-home-win';

    public const URL = 'https://www.cafonline.com/afcon2025/news/totalenergies-caf-afcon-2027-qualifiers-dr-congo-extend-perfect-run-as-guinea-bissau-and-zimbabwe-held-to-draws/';

    public function attest(string $event, string $condition): array
    {
        $fixture = $event === self::EVENT ? [
            'report' => self::URL, 'report_teams' => ['DR Congo', 'Sierra Leone'], 'kickoff' => '2026-11-12T16:00:00Z',
        ] : (config('caf_qualifiers')[$event] ?? null);
        if ($fixture === null || $condition !== self::CONDITION) {
            throw new RuntimeException('Rencontre ou condition CAF non prise en charge.');
        }

        $url = $fixture['report'];
        if ($url === null) {
            return compact('event', 'condition') + ['status' => 'pending'];
        }
        if (! is_string($url) || ! preg_match('~^https://www\.cafonline\.com/afcon2025/news/[a-z0-9-]+/$~D', $url)) {
            throw new RuntimeException('URL du rapport CAF invalide.');
        }

        $response = Http::connectTimeout(3)->timeout(8)->withoutRedirecting()->get($url);
        if ($response->notFound()) {
            return compact('event', 'condition') + ['status' => 'pending'];
        }
        if (! $response->successful() || strlen($response->body()) > 2_000_000) {
            throw new RuntimeException('Rapport CAF indisponible.');
        }

        $document = new \DOMDocument;
        if (! $document->loadHTML($response->body(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('Rapport CAF illisible.');
        }
        $dates = [];
        foreach ($document->getElementsByTagName('time') as $time) {
            $dates[] = trim($time->textContent);
        }
        [$first, $second] = array_map(fn ($team) => preg_quote($team, '~'), $fixture['report_teams']);
        $date = CarbonImmutable::parse($fixture['kickoff'])->format('l, j F Y');
        $scores = [];
        foreach ($document->getElementsByTagName('h2') as $heading) {
            if (preg_match("~^($first|$second) (\d{1,2})\s*[-–]\s*(\d{1,2}) ($first|$second)(?::|$)~iu", str_replace("'", '’', trim($heading->textContent)), $match)
                && strcasecmp($match[1], $match[4]) !== 0) {
                $score = [(int) $match[2], (int) $match[3]];
                $scores[] = strcasecmp($match[1], $fixture['report_teams'][0]) === 0 ? $score : array_reverse($score);
            }
        }
        // This group-stage fixture has no extra time or penalty shootout.
        if (! in_array($date, $dates, true) || count($scores) !== 1) {
            throw new RuntimeException('Date ou score CAF absent ou ambigu.');
        }
        [$home, $away] = $scores[0];

        return compact('event', 'condition') + [
            'status' => 'final',
            'value' => $home > $away ? 'home_win' : 'no_home_win',
            'official_url' => $url,
            'verified_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'score' => "$home-$away",
            'document_sha256' => hash('sha256', $response->body()),
        ];
    }
}
