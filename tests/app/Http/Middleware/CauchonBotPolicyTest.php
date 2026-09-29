<?php

declare(strict_types=1);

namespace Fisharebest\Webtrees\Http\Middleware;

use Fisharebest\Webtrees\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CauchonBotPolicy::class)]
class CauchonBotPolicyTest extends TestCase
{
    public function testPublicSearchAndSharingAgentsCanPassTheBlockList(): void
    {
        $user_agents = [
            'Google-InspectionTool/1.0',
            'Googlebot-Image/1.0',
            'Applebot/0.1',
            'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
            'BingPreview/1.0',
            'DuckAssistBot/1.2; (+http://duckduckgo.com/duckassistbot.html)',
            'OAI-SearchBot/1.0 (+https://openai.com/searchbot)',
            'ChatGPT-User/1.0 (+https://openai.com/bot)',
            'Claude-SearchBot/1.0',
            'Claude-User/1.0',
            'PerplexityBot/1.0',
            'Perplexity-User/1.0',
            'MistralAI-User/1.0',
            'Discordbot/2.0',
            'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
            'facebookexternalhit/1.1',
            'Facebot',
            'Google-Read-Aloud',
            'Feedly/1.0',
            'Mozilla/5.0 Konqueror/22.12 Safari/537.36',
            'Citoid/1.0',
            'IABot/1.0',
            'BufferLinkPreviewBot/1.0',
            'Iframely/1.0',
            'Archive-It/1.0',
            'archive.org_bot/1.0',
            'Arquivo-web-crawler/1.0',
            'Chrome-Lighthouse/1.0',
            'W3C_CSS_Validator/1.0',
            'Googlebot-News',
            'UptimeRobot/2.0',
            'GenericSpider/1.0',
            'ExampleOpenAIClient/1.0',
        ];

        foreach ($user_agents as $user_agent) {
            self::assertFalse(CauchonBotPolicy::isBlocked($user_agent), $user_agent);
        }
    }

    public function testReviewedEntriesAreRemovedFromTheEffectiveBlockList(): void
    {
        $removed = [
            'Google-Read-Aloud', 'Feedly', 'Feedbin', 'Inoreader', 'inoreader.com',
            'NewsBlur', 'FreshRSS', 'CommaFeed', 'FeedFlow', 'Instapaper', 'Konqueror',
            'Citoid', 'WMF Zotero Translation Server', 'ZoteroTranslationServer', 'IABot',
            'BufferLinkPreviewBot', 'Iframely', 'Embedly', 'ClickUpLinkUnfurler',
            'Archive-It', 'archive.org_bot', 'Arquivo-web-crawler',
            'Chrome-Lighthouse', 'W3 Validator Services', 'W3C_CSS_Validator',
            'W3C-mobileOK', 'W3C_Unicorn', 'SecurityHeaders', 'SSL Labs',
            'Google-Site-Verification', 'Googlebot-News',
            'aa', '008', 'dbot', 'Spider', 'Uptime', 'OpenAI',
            'Feedfetcher-Google', 'Google-NotebookLM', 'NotebookLM',
            'Facebot',
        ];

        foreach ($removed as $robot) {
            self::assertNotContains($robot, CauchonBotPolicy::blockedRobots(), $robot);
        }

        self::assertCount(1_308, CauchonBotPolicy::blockedRobots());
    }

    public function testSpecificCrawlerBlocksSurviveGenericRuleRetirement(): void
    {
        foreach (['Screaming Frog SEO Spider/1.0', 'ChatGLM-Spider/1.0', 'Better Uptime/1.0', 'GPTBot/1.0 Feedly/1.0'] as $user_agent) {
            self::assertTrue(CauchonBotPolicy::isBlocked($user_agent), $user_agent);
        }
    }

    public function testMultiServicePreviewRequiresAllFourExactMarkers(): void
    {
        $multi_service = 'Mozilla/5.0 Safari/537.36 facebookexternalhit/1.1 Facebot Twitterbot/1.0';

        self::assertTrue(CauchonBotPolicy::isMultiServicePreview($multi_service));

        foreach ([
            'Mozilla/5.0 Safari/537.36 facebookexternalhit/1.1',
            'Mozilla/5.0 Safari/537.36 Facebot',
            'Mozilla/5.0 Safari/537.36 Twitterbot/1.0',
            'facebookexternalhit/1.1 Facebot Twitterbot/1.0',
            'Mozilla/5.0 safari/537.36 facebookexternalhit/1.1 Facebot Twitterbot/1.0',
            'Mozilla/5.0 Safari/537.36 FacebookExternalHit/1.1 Facebot Twitterbot/1.0',
        ] as $user_agent) {
            self::assertFalse(CauchonBotPolicy::isMultiServicePreview($user_agent), $user_agent);
        }
    }

    public function testTrainingAgentsStayBlocked(): void
    {
        $user_agents = [
            'GPTBot/1.3',
            'ClaudeBot/1.0',
            'anthropic-ai',
            'Google-Extended',
            'MistralAI-Training/1.0',
            'meta-externalagent/1.0',
            'Meta-ExternalAgent/1.0',
            'FacebookBot/1.0',
            'CCBot/2.0',
            'Bytespider',
            'AI2Bot/1.0',
            'Ai2Bot-Dolma/1.0',
            'cohere-training-data-crawler',
            'Amazonbot/0.1',
        ];

        foreach ($user_agents as $user_agent) {
            self::assertTrue(CauchonBotPolicy::isBlocked($user_agent), $user_agent);
        }
    }
}
