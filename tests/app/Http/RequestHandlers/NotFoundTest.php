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

namespace Fisharebest\Webtrees\Http\RequestHandlers;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Middleware\BadBotBlocker;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;

use function route;

#[CoversClass(NotFound::class)]
class NotFoundTest extends TestCase
{
    public function testClass(): void
    {
        self::assertTrue(class_exists(NotFound::class));
    }

    public function testRobotBareHomePageRedirects(): void
    {
        foreach (['/', '/index.php'] as $path) {
            $request = self::createRequest()
                ->withAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME, true);
            $request = $request->withUri($request->getUri()->withPath($path));

            $response = (new NotFound())->handle($request);

            self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
            self::assertSame(route(HomePage::class), $response->getHeaderLine('location'));
            self::assertTrue(Registry::container()->get(ServerRequestInterface::class)
                ->getAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME));
        }
    }

    public function testRobotSubdirectoryHomePageRedirects(): void
    {
        foreach (['/family', '/family/', '/family/index.php'] as $path) {
            $request = self::createRequest()
                ->withAttribute('base_url', 'https://webtrees.test/family')
                ->withAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME, true);
            $request = $request->withUri($request->getUri()->withPath($path));

            $response = (new NotFound())->handle($request);

            self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        }
    }

    public function testRobotUnknownPathAndExplicitRouteRemainNotFound(): void
    {
        $request = self::createRequest()
            ->withAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME, true);
        $request = $request->withUri($request->getUri()->withPath('/unknown'));

        $response = (new NotFound())->handle($request);
        self::assertSame(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());

        $request = self::createRequest()
            ->withAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME, true);
        $request = $request->withUri($request->getUri()->withQuery('route=%2Ftree%2Fexample-tree'));

        $response = (new NotFound())->handle($request);
        self::assertSame(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    public function testRobotNonGetRemainsNotFound(): void
    {
        $request = self::createRequest(RequestMethodInterface::METHOD_POST)
            ->withAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME, true);
        $request = $request->withUri($request->getUri()->withPath('/'));

        $response = (new NotFound())->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    public function testHumanNonGetStillThrows(): void
    {
        $request = self::createRequest(RequestMethodInterface::METHOD_POST);
        $request = $request->withUri($request->getUri()->withPath('/'));

        $this->expectException(HttpNotFoundException::class);
        (new NotFound())->handle($request);
    }
}
