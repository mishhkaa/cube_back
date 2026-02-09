<?php

namespace App\Console\Commands\Notifications;

use App\Models\Notice;
use App\Notifications\UpWorkJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class UpWorkJobsInSlack extends Command
{
    protected $signature = 'notifications:upwork-slack';

    protected const string NOTICE_TYPE_NAME = 'upwork-slack';

    protected const string SLACK_SEARCH_URL = 'https://mediangrp.slack.com/search/search-';

    protected Carbon $dateTimeFrom;
    protected Carbon $dateTimeTo;

    public function handle(): void
    {
        $this->initPeriod();
        $notices = Notice::whereType(self::NOTICE_TYPE_NAME)->active()->get();

        foreach ($notices as $notice) {
            if (empty($notice->config['chat']) || empty($notice->config['rss'])) {
                $notice->disable();
                continue;
            }

            $jobs = $this->getJobs($notice->config['rss']);

            if (is_null($jobs)){
                $notice->disable();
                continue;
            }

            foreach ($jobs as $job) {
                if (empty($job['pubDate']) || !$this->isNewJob($job['pubDate'])) {
                    continue;
                }

                $this->sendMessage($notice->config['chat'], $job['title'], $job['link'], $job['description'], $notice->config['tags'] ?? []);

                sleep(2);
            }
        }
    }

    protected function initPeriod(): void
    {
        $remainder = (int)date('i') % 10;
        $this->dateTimeFrom = now()->subMinutes(10)->subMinutes($remainder)->setSecond(0)->setTimezone('UTC');
        $this->dateTimeTo = now()->subMinutes($remainder)->setSecond(0)->setTimezone('UTC');
    }

    protected function getJobs($link): array|null
    {
        try {
            return retry(2, static function () use ($link) {
                $xml = simplexml_load_string(file_get_contents($link), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
                return json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)['channel']['item'] ?? [];
            }, 2000);
        } catch (Throwable $exception) {
            report($exception);
            return null;
        }
    }

    protected function isNewJob(string $dateTime): bool
    {
        return Carbon::parse($dateTime)->between($this->dateTimeFrom, $this->dateTimeTo);
    }

    protected function sendMessage($channel, $title, $link, $desc, $tags): void
    {
        $title = preg_replace('/[\r\n]/', '', $title);
        $message = "<$link|*$title*>".PHP_EOL.PHP_EOL;
        $message .= $this->parseDesc($desc);
        $message .= $this->implodeTags($tags);

        Notification::route('slack', $channel)
            ->notifyNow(new UpWorkJob($message));
    }

    protected function implodeTags($tags): string
    {
        return collect($tags)->map(function ($tag) {
            $json = json_encode([
                'd' => urlencode("#$tag"),
                'r' => urlencode("#$tag")
            ]);
            return "<".self::SLACK_SEARCH_URL.base64_encode($json)."|#$tag>";
        })->join('  ');
    }

    protected function parseDesc($desc): string
    {
        $desc = htmlspecialchars_decode($desc, ENT_QUOTES);
        $desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5);

        if (mb_strlen($desc) > 1000) {
            $split = explode('<br /><br /><b>', $desc);
            if (count($split) === 2) {
                $end = '<b>'.$split[1];
                $desc = $split[0].'<br /><br />';
            }
            if (count($split) > 2) {
                $end = '<b>'.array_pop($split);
                $desc = implode('<br/>', $split);
            }
        }
        $desc = $this->formatToSlack($desc);
        if (isset($end)) {
            $end = $this->formatToSlack($end);
            if (mb_strlen($desc) > 1000) {
                $desc = mb_substr($desc, 0, 1000).'...';
            }
            $desc .= PHP_EOL.PHP_EOL.$end;
        }

        return $desc.PHP_EOL.PHP_EOL;
    }

    protected function formatToSlack($text): string
    {
        $text = preg_replace('/<a[^<]*<\/a>$/', '', $text);

        $text = str_replace(
            ['<br />', '</b>:', '</b>:  ', '<b>', '</b>', '    '],
            [PHP_EOL, '</b>: ', '</b>: ', '*', '*', ' '],
            $text
        );
        $text = preg_replace('/[\r\n]+?( *)+?\s*/', PHP_EOL.PHP_EOL, $text);
        $text = strip_tags($text);
        return trim($text);
    }
}
