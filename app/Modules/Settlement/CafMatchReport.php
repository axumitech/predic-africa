<?php

namespace App\Modules\Settlement;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CafMatchReport
{
    public const EVENT = 'afcon-2027-md3-cod-sle-2026-11-12';

    public const CONDITION = 'regulation-home-win';

    public const URL = 'https://www.cafonline.com/afcon2025/news/totalenergies-caf-afcon-2027-qualifiers-dr-congo-extend-perfect-run-as-guinea-bissau-and-zimbabwe-held-to-draws/';

    public function attest(string $event, string $condition): array
    {
        if ($event !== self::EVENT || $condition !== self::CONDITION) {
            throw new RuntimeException('Rencontre ou condition CAF non prise en charge.');
        }

        $response = Http::connectTimeout(3)->timeout(8)->withoutRedirecting()->get(self::URL);
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
        $scores = [];
        foreach ($document->getElementsByTagName('h2') as $heading) {
            if (preg_match('/^DR Congo (\d{1,2})\s*[-–]\s*(\d{1,2}) Sierra Leone:/u', trim($heading->textContent), $match)) {
                $scores[] = [(int) $match[1], (int) $match[2]];
            }
        }
        // This group-stage fixture has no extra time or penalty shootout.
        if (! in_array('Thursday, 12 November 2026', $dates, true) || count($scores) !== 1) {
            throw new RuntimeException('Date ou score CAF absent ou ambigu.');
        }
        [$home, $away] = $scores[0];

        return compact('event', 'condition') + [
            'status' => 'final',
            'value' => $home > $away ? 'home_win' : 'no_home_win',
            'official_url' => self::URL,
            'verified_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'score' => "$home-$away",
            'document_sha256' => hash('sha256', $response->body()),
        ];
    }
}
