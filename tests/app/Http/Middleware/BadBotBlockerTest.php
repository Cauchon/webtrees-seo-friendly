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
