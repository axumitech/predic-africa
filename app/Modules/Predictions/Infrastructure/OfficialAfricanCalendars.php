<?php

namespace App\Modules\Predictions\Infrastructure;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use UnexpectedValueException;

class OfficialAfricanCalendars
{
    public const CAF = 'https://www.cafonline.com/umbraco/api/Article/News';

    public const AU = 'https://au.int/en/happening/upcoming';

    public const SARB = 'https://www.resbank.co.za/en/home/what-we-do/monetary-policy/MPC-announcement-webcasts';

    public function discover(?string $category = null): array
    {
        $sources = [
            'CAF' => [$this->football(...), ['Sport']],
            'Union africaine' => [$this->union(...), ['Économie', 'Politique', 'Société']],
            'SARB' => [$this->monetaryPolicy(...), ['Économie']],
        ];
        $events = [];
        $unavailable = [];
        foreach ($sources as $name => [$fetch, $categories]) {
            if ($category !== null && ! in_array($category, $categories, true)) {
                continue;
            }
            try {
                $accepted = 0;
                foreach ($fetch() as $id => $event) {
                    if (($category === null || $event['category'] === $category)
                        && $event['closes_at']->gt(now()) && $event['closes_at']->lte(now()->addDays(120))) {
                        $events[$id] = $event;
                        if (++$accepted === 50) {
                            break;
                        }
                    }
                }
            } catch (ConnectionException|RequestException|UnexpectedValueException) {
                $unavailable[] = $name;
            }
        }

        uasort($events, fn ($a, $b) => $a['closes_at'] <=> $b['closes_at']);

        return ['events' => $events, 'unavailable' => $unavailable];
    }

    private function football(): array
    {
        $body = $this->fetch(self::CAF, ['contextId' => 1249, 'itemsPerPage' => 24, 'pageNumber' => 1, 'culture' => 'fr']);
        $articles = json_decode($body, true)['items'] ?? null;
        if (! is_array($articles)) {
            throw new UnexpectedValueException('CAF news list not found.');
        }

        $events = [];
        $selected = 0;
        foreach ($articles as $article) {
            if (! is_array($article) || ! is_string($article['title'] ?? null)
                || ! preg_match('/(?:Éliminatoires|Qualifications) de la CAN (20\d{2})/iu', $article['title'], $competition)) {
                continue;
            }
            $path = $article['url'] ?? null;
            $published = is_string($article['date'] ?? null) ? $this->date(substr($article['date'], 0, 10), 'Y-m-d', 'UTC') : null;
            if (! $published || $published->lt(now()->subDays(90)) || $published->gt(now()->addDay())
                || ! is_string($path) || ! preg_match('~\A/fr/can2025/infos/[a-z0-9-]+/\z~D', $path)) {
                continue;
            }
            if ($selected++ >= 3) {
                break;
            }
            $url = 'https://www.cafonline.com'.$path;
            $page = $this->html($url);
            $matchday = null;
            foreach ($page->query('//article//p') as $paragraph) {
                $text = Str::squish($paragraph->textContent);
                if (preg_match('/^PROGRAMME DE LA (\d+)(?:E|ÈRE) JOURNÉE \(TOUS LES HORAIRES EN GMT\)$/iu', $text, $header)) {
                    $matchday = (int) $header[1];
                    continue;
                }
                if ($matchday === null || ! preg_match('/^•?\s*Groupe [A-Z]\s*:\s*(.+)$/u', $text, $group)) {
                    continue;
                }
                foreach (preg_split('/,\s*(?![^()]*\))/u', $group[1]) as $fixture) {
                    if (! preg_match('/^\s*([\p{L}\p{M} .’\'-]{2,55})\s+[–—-]\s+([\p{L}\p{M} .’\'-]{2,55})\s*\((\d{2})\/(\d{2}),?\s*(\d{1,2})h(\d{2})\)\s*$/u', trim($fixture), $match)) {
                        continue;
                    }
                    $monthOffset = (int) $match[4] - $published->month;
                    $year = $published->year + match (true) {
                        $monthOffset < -6 => 1,
                        $monthOffset > 6 => -1,
                        default => 0,
                    };
                    $date = sprintf('%04d-%s-%s %02d:%s', $year, $match[4], $match[3], $match[5], $match[6]);
                    $starts = $this->date($date, 'Y-m-d H:i', 'UTC');
                    if (! $starts) {
                        continue;
                    }
                    [$home, $away] = [trim($match[1]), trim($match[2])];
                    $key = 'caf-'.$competition[1].'-'.$matchday.'-'.Str::slug($home).'-'.Str::slug($away);
                    $events[$key] ??= [
                        'category' => 'Sport',
                        'title' => "$home – $away · Victoire de la première équipe dans le temps réglementaire ?",
                        'rules' => "Oui si $home gagne à l’issue du temps réglementaire, arrêts de jeu compris. Non en cas de nul ou de victoire de $away. Si le match est reporté ou annulé, annuler le marché.",
                        'closes_at' => $starts->subMinutes(5),
                        'announcement' => $url,
                        'result' => 'https://www.cafonline.com/fr/can2025/',
                    ];
                }
            }
        }

        return $events;
    }

    private function union(): array
    {
        $page = $this->html(self::AU);
        $rows = $page->query('//div[contains(concat(" ", normalize-space(@class), " "), " views-row ")][.//a[starts-with(@href, "/en/newsevents/")]]');
        if ($rows->length === 0 && ! $page->evaluate('boolean(//h1[contains(., "Upcoming News & Events")])')) {
            throw new UnexpectedValueException('AU event list not found.');
        }
        $events = [];
        foreach ($rows as $row) {
            $path = $page->evaluate('string(.//div[contains(@class, "views-field-field-short-title")]//a/@href)', $row);
            $title = Str::squish($page->evaluate('string(.//div[contains(@class, "views-field-field-short-title")]//a)', $row));
            $datetime = $page->evaluate('string(.//div[contains(@class, "views-field-field-date")]//time/@datetime)', $row);
            if (! preg_match('~\A/en/newsevents/\d{8}/([a-z0-9-]+)\z~D', $path, $slug)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}T/', $datetime) || mb_strlen($title) < 10 || mb_strlen($title) > 145) {
                continue;
            }
            $day = $this->date(substr($datetime, 0, 10), 'Y-m-d', 'UTC');
            if (! $day) {
                continue;
            }
            $category = match (true) {
                (bool) preg_match('/\b(council|assembly|election|diplomatic|summit)\b/i', $title) => 'Politique',
                (bool) preg_match('/\b(economic|finance|financing|trade|investment)\b/i', $title) => 'Économie',
                default => 'Société',
            };
            $date = $day->locale('fr')->translatedFormat('j F Y');
            $deadline = $day->addDays(7)->format('d/m/Y');
            $events['au-'.$day->year.'-'.$slug[1]] = [
                'category' => $category,
                'title' => "« $title » débutera-t-il le $date ?",
                'rules' => "Oui si l’Union africaine confirme l’ouverture de l’événement le $date, à l’heure locale du lieu de l’événement. Non si elle confirme son annulation, son report ou une autre date d’ouverture. Sans publication officielle permettant de trancher avant le $deadline à 23 h 59 UTC, annuler le marché.",
                'closes_at' => $day->subHours(12),
                'announcement' => 'https://au.int'.$path,
                'result' => 'https://au.int/en/press-releases',
            ];
        }

        return $events;
    }

    private function monetaryPolicy(): array
    {
        $page = $this->html(self::SARB);
        $events = [];
        $rows = $page->query('//tr[td[contains(., "press conference of the Monetary Policy Committee")]]');
        if ($rows->length === 0) {
            throw new UnexpectedValueException('SARB conference rows not found.');
        }
        foreach ($rows as $row) {
            $label = Str::squish($page->evaluate('string(./th[1])', $row));
            if (! preg_match('/^(\d{4}\/\d{2}\/\d{2})\s*-\s*(\d{2}:\d{2})$/', $label, $match)) {
                continue;
            }
            $starts = $this->date("$match[1] $match[2]", 'Y/m/d H:i', 'Africa/Johannesburg');
            if (! $starts) {
                continue;
            }
            $date = $starts->locale('fr')->translatedFormat('j F Y');
            $events['sarb-'.$starts->format('Y-m')] = [
                'category' => 'Économie',
                'title' => "La banque centrale sud-africaine baissera-t-elle son taux directeur le $date ?",
                'rules' => "Comparer le taux repo annoncé par la SARB le $date au taux en vigueur avant cette décision. Oui si le taux baisse. Non s’il est maintenu ou relevé. Si la décision est reportée ou annulée, annuler le marché.",
                'closes_at' => $starts->utc()->subMinutes(5),
                'announcement' => self::SARB,
                'result' => 'https://www.resbank.co.za/en/home/what-we-do/monetary-policy/monetary-policy-committee',
            ];
        }

        return $events;
    }

    private function date(string $value, string $format, string $zone): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value, $zone);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $date && $date->format($format) === $value ? $date : null;
    }

    private function html(string $url): DOMXPath
    {
        $document = new DOMDocument;
        if (! $document->loadHTML('<?xml encoding="UTF-8">'.$this->fetch($url), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new UnexpectedValueException('Invalid official calendar.');
        }

        return new DOMXPath($document);
    }

    private function fetch(string $url, array $query = []): string
    {
        $response = Http::withHeaders(['User-Agent' => 'PredicAfrica-Calendar/1.0'])
            ->connectTimeout(3)->timeout(8)->withoutRedirecting()->get($url, $query)->throw();
        $body = $response->body();
        if (! $response->successful() || $body === '' || strlen($body) > 2_000_000) {
            throw new UnexpectedValueException('Unsupported official calendar response.');
        }

        return $body;
    }
}
