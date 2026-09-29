<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Http\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\NetworkService;
use Fisharebest\Webtrees\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;

#[CoversClass(BadBotBlocker::class)]
class BadBotBlockerTest extends TestCase
{
    public function testClass(): void
    {
        self::assertTrue(class_exists(BadBotBlocker::class));
    }

    public function testFirstVisitPreservesPageAndSessionCookie(): void
    {
        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->willReturn(response('Requested page', StatusCodeInterface::STATUS_OK, [
                'set-cookie' => 'session=abc; Path=/; HttpOnly',
            ]));

        $request = self::browserRequest();

        $middleware = new BadBotBlocker(self::createStub(NetworkService::class));
        $response   = $middleware->process($request, $handler);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('Requested page', (string) $response->getBody());
        self::assertSame([
            'session=abc; Path=/; HttpOnly',
            'x=y; Path=/; HttpOnly; SameSite=Strict',
        ], $response->getHeader('set-cookie'));
    }

    public function testReturningBrowserDoesNotReceiveTestCookie(): void
    {
        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->willReturn(response('Requested page'));

        $request = self::browserRequest()->withCookieParams(['x' => 'y']);

        $middleware = new BadBotBlocker(self::createStub(NetworkService::class));
        $response   = $middleware->process($request, $handler);

        self::assertSame('Requested page', (string) $response->getBody());
        self::assertSame([], $response->getHeader('set-cookie'));
    }

    public function testWordPressScanIsBlockedBeforeCookieIsSet(): void
    {
        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $request = self::browserRequest();
        $request = $request->withUri($request->getUri()->withPath('/wp-login.php'));

        $middleware = new BadBotBlocker(self::createStub(NetworkService::class));
        $response   = $middleware->process($request, $handler);

        self::assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
        self::assertSame([], $response->getHeader('set-cookie'));
    }

    public function testMixedPreviewIsRestrictedButCanFetchThePage(): void
    {
        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with(self::callback(static fn (ServerRequestInterface $request): bool =>
                $request->getAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME) === true))
            ->willReturn(response('Shared page'));

        $request = self::browserRequest(self::mixedPreviewUserAgent());

        $middleware = new BadBotBlocker(self::createStub(NetworkService::class));
        $response   = $middleware->process($request, $handler);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('Shared page', (string) $response->getBody());
        self::assertSame(['x=y; Path=/; HttpOnly; SameSite=Strict'], $response->getHeader('set-cookie'));
    }

    public function testMixedPreviewWithTrainingBotIsBlocked(): void
    {
        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $request    = self::browserRequest(self::mixedPreviewUserAgent() . ' GPTBot');
        $middleware = new BadBotBlocker(self::createStub(NetworkService::class));
        $response   = $middleware->process($request, $handler);

        self::assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
        self::assertSame('Not acceptable: bad-ua', (string) $response->getBody());
    }

    public function testOrdinaryFacebookPreviewStillRequiresFacebookAsn(): void
    {
        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $network_service = self::createStub(NetworkService::class);
        $network_service->method('findIpRangesForAsn')->willReturn([]);

        $request    = self::browserRequest('facebookexternalhit/1.1');
        $middleware = new BadBotBlocker($network_service);
        $response   = $middleware->process($request, $handler);

        self::assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
        self::assertSame('Not acceptable: bad-dns', (string) $response->getBody());
    }

    /** @param list<string> $addresses */
    #[DataProvider('crawlerDnsProvider')]
    public function testCrawlerDns(string $ip, string|false $host, array $addresses, bool $allowed, bool $forward_lookup): void
    {
        $middleware = self::getMockBuilder(BadBotBlocker::class)
            ->setConstructorArgs([self::createStub(NetworkService::class)])
            ->onlyMethods(['reverseDns', 'forwardDns'])
            ->getMock();
        $middleware->expects(self::once())->method('reverseDns')->with($ip)->willReturn($host);
        $middleware->expects($forward_lookup ? self::once() : self::never())
            ->method('forwardDns')->with($host)->willReturn($addresses);

        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects($allowed ? self::once() : self::never())
            ->method('handle')
            ->with(self::callback(static fn (ServerRequestInterface $request): bool =>
                $request->getAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME) === true))
            ->willReturn(response('Public record'));

        // Cookies prevent the generic no-cookie heuristic from masking missing classification.
        $request = self::browserRequest('Googlebot/2.1')
            ->withAttribute('client-ip', $ip)
            ->withCookieParams(['x' => 'y']);
        $response = $middleware->process($request, $handler);

        self::assertSame($allowed ? StatusCodeInterface::STATUS_OK : StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
        self::assertSame($allowed ? 'Public record' : 'Not acceptable: bad-dns', (string) $response->getBody());
    }

    /** @return iterable<string, array{string, string|false, list<string>, bool, bool}> */
    public static function crawlerDnsProvider(): iterable
    {
        yield 'IPv4' => ['192.0.2.1', 'crawl.googlebot.com', ['192.0.2.1'], true, true];
        yield 'IPv6' => ['2001:db8::1', 'crawl.googlebot.com', ['2001:db8::1'], true, true];
        yield 'expanded IPv6 answer' => ['2001:db8::1', 'crawl.googlebot.com', ['2001:0db8:0000:0000:0000:0000:0000:0001'], true, true];
        yield 'expanded IPv6 request' => ['2001:0db8:0000:0000:0000:0000:0000:0001', 'crawl.googlebot.com', ['2001:db8::1'], true, true];
        yield 'second IPv4 answer' => ['192.0.2.1', 'crawl.googlebot.com', ['192.0.2.2', '192.0.2.1'], true, true];
        yield 'second mixed-family answer' => ['2001:db8::1', 'crawl.googlebot.com', ['192.0.2.1', '2001:db8::1'], true, true];
        yield 'mismatched forward answer' => ['192.0.2.1', 'crawl.googlebot.com', ['192.0.2.2'], false, true];
        yield 'missing forward answer' => ['192.0.2.1', 'crawl.googlebot.com', [], false, true];
        yield 'missing reverse answer' => ['192.0.2.1', false, [], false, false];
        yield 'unresolved reverse address' => ['192.0.2.1', '192.0.2.1', [], false, false];
        yield 'untrusted suffix' => ['192.0.2.1', 'googlebot.com.example.org', [], false, false];
        yield 'domain boundary' => ['192.0.2.1', 'notgooglebot.com', [], false, false];
        yield 'untrusted GCP fetcher' => ['192.0.2.1', 'fetch.gae.googleusercontent.com', [], false, false];
    }

    public function testReverseOnlyCrawlerDoesNotRequireForwardDns(): void
    {
        $middleware = self::getMockBuilder(BadBotBlocker::class)
            ->setConstructorArgs([self::createStub(NetworkService::class)])
            ->onlyMethods(['reverseDns', 'forwardDns'])
            ->getMock();
        $middleware->expects(self::once())->method('reverseDns')->with('192.0.2.1')->willReturn('crawl.baidu.com');
        $middleware->expects(self::never())->method('forwardDns');

        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')
            ->with(self::callback(static fn (ServerRequestInterface $request): bool =>
                $request->getAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME) === true))
            ->willReturn(response('Public record'));

        $request = self::browserRequest('Baiduspider/2.0')
            ->withAttribute('client-ip', '192.0.2.1')
            ->withCookieParams(['x' => 'y']);

        self::assertSame(StatusCodeInterface::STATUS_OK, $middleware->process($request, $handler)->getStatusCode());
    }

    private static function mixedPreviewUserAgent(): string
    {
        return 'Mozilla/5.0 Safari/537.36 facebookexternalhit/1.1 Facebot Twitterbot/1.0';
    }

    private static function browserRequest(string $user_agent = 'Mozilla/5.0 Chrome/120.0'): ServerRequestInterface
    {
        $factory = Registry::container()->get(ServerRequestFactoryInterface::class);
        self::assertInstanceOf(ServerRequestFactoryInterface::class, $factory);

        return $factory->createServerRequest('GET', 'https://webtrees.test/', [
            'HTTP_USER_AGENT' => $user_agent,
        ])->withAttribute('client-ip', '127.0.0.1');
    }
}
