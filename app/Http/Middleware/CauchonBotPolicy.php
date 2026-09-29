<?php

declare(strict_types=1);

namespace Fisharebest\Webtrees\Http\Middleware;

use function array_filter;
use function array_unique;
use function array_values;
use function str_contains;

/** Keep this site policy separate from the upstream BAD_ROBOTS list. */
final class CauchonBotPolicy
{
    // Reviewed exact upstream entries with public reading, sharing, or diagnostic value.
    private const array PUBLIC_ROBOT_EXCEPTIONS = [
        'Applebot' => true,
        'Archive-It' => true,
        'archive.org_bot' => true,
        'Arquivo-web-crawler' => true,
        'Baiduspider' => true,
        'Bing Preview' => true,
        'BingPreview' => true,
        'Bluesky' => true,
        'Bravebot' => true,
        'BufferLinkPreviewBot' => true,
        'ChatGPT-User' => true,
        'Chrome-Lighthouse' => true,
        'Citoid' => true,
        'ClickUpLinkUnfurler' => true,
        'Claude-SearchBot' => true,
        'Claude-User' => true,
        'CommaFeed' => true,
        'Daum' => true,
        'Discordbot' => true,
        'DuckAssistBot' => true,
        'DuckDuckBot' => true,
        'Embedly' => true,
        'facebook' => true,
        'Facebot' => true,
        'Feedbin' => true,
        'FeedFlow' => true,
        'Feedly' => true,
        'Feedfetcher-Google' => true,
        'FreshRSS' => true,
        'Google Inspection Tool' => true,
        'Google-InspectionTool' => true,
        'Google-NotebookLM' => true,
        'Google-Read-Aloud' => true,
        'Google-Site-Verification' => true,
        'Googlebot-Image' => true,
        'Googlebot-News' => true,
        'Googlebot-Video' => true,
        'IABot' => true,
        'Iframely' => true,
        'inoreader.com' => true,
        'Inoreader' => true,
        'Instapaper' => true,
        'Kagibot' => true,
        'Konqueror' => true,
        'LinkedInBot' => true,
        'Mastodon' => true,
        'Microsoft Preview' => true,
        'MicrosoftPreview' => true,
        'MistralAI-User' => true,
        'mojeek' => true,
        'MojeekBot' => true,
        'msnbot' => true,
        'NaverBot' => true,
        'NewsBlur' => true,
        'NotebookLM' => true,
        'OAI-SearchBot' => true,
        'Perplexity-User' => true,
        'PerplexityBot' => true,
        'PetalBot' => true,
        'Pinterest' => true,
        'Qwantbot-official' => true,
        'Qwantify' => true,
        'redditbot' => true,
        'SecurityHeaders' => true,
        'Seznam' => true,
        'Skype' => true,
        'Slack Image Proxy' => true,
        'Slack-ImgProxy' => true,
        'Slackbot' => true,
        'slurp' => true,
        'Snapchat' => true,
        'Sogou' => true,
        'SSL Labs' => true,
        'Telegram Bot' => true,
        'TelegramBot' => true,
        'Tumblr' => true,
        'Twitterbot' => true,
        'Viber' => true,
        'W3 Validator Services' => true,
        'W3C_CSS_Validator' => true,
        'W3C-mobileOK' => true,
        'W3C_Unicorn' => true,
        'WhatsApp' => true,
        'WMF Zotero Translation Server' => true,
        'Yahoo Japan' => true,
        'Yahoo Link Preview' => true,
        'Yahoo! Slurp' => true,
        'YahooMailProxy' => true,
        'yandex' => true,
        'YandexBot' => true,
        'YandexImages' => true,
        'YandexMobileBot' => true,
        'YandexRenderResourcesBot' => true,
        'YandexVideo' => true,
        'Yeti' => true,
        'YouBot' => true,
        'ZoteroTranslationServer' => true,
    ];

    // Whole-header substring matches on these generic strings reject unrelated clients.
    private const array RETIRED_GENERIC_ROBOTS = [
        '008' => true,
        'aa' => true,
        'dbot' => true,
        'OpenAI' => true,
        'Spider' => true,
        'Uptime' => true,
    ];

    // Keep model-training and data-collection agents blocked even if upstream removes them.
    private const array TRAINING_ROBOTS = [
        'GPTBot',
        'ClaudeBot',
        'anthropic-ai',
        'Google-Extended',
        'MistralAI-Training',
        'meta-externalagent',
        'Meta-ExternalAgent',
        'FacebookBot',
        'CCBot',
        'Bytespider',
        'AI2Bot',
        'Ai2Bot-Dolma',
        'cohere-training-data-crawler',
        'Amazonbot',
    ];

    /** @var null|array<string> */
    private static ?array $blocked_robots = null;

    /** @return array<string> */
    public static function blockedRobots(): array
    {
        return self::$blocked_robots ??= array_values(array_unique([
            ...array_filter(
                BadBotBlocker::BAD_ROBOTS,
                static fn (string $robot): bool => !isset(self::PUBLIC_ROBOT_EXCEPTIONS[$robot])
                    && !isset(self::RETIRED_GENERIC_ROBOTS[$robot]),
            ),
            ...self::TRAINING_ROBOTS,
        ]));
    }

    public static function isBlocked(string $ua): bool
    {
        foreach (self::blockedRobots() as $robot) {
            if (str_contains($ua, $robot)) {
                return true;
            }
        }

        return false;
    }

    public static function isMultiServicePreview(string $ua): bool
    {
        return str_contains($ua, 'Safari/')
            && str_contains($ua, 'facebookexternalhit/')
            && str_contains($ua, 'Facebot')
            && str_contains($ua, 'Twitterbot/');
    }
}
