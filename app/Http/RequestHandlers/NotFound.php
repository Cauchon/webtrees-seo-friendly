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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function in_array;
use function parse_str;
use function parse_url;
use function redirect;
use function response;
use function rtrim;
use function route;

use const PHP_URL_PATH;

final class NotFound implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // A bare site URL has no route yet. Let robots reach the public home page.
        if ($request->getAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME) !== null) {
            parse_str($request->getUri()->getQuery(), $uri_query);
            $base_path  = rtrim(parse_url((string) $request->getAttribute('base_url', ''), PHP_URL_PATH) ?? '', '/');
            $home_paths = [$base_path . '/', $base_path . '/index.php'];

            if ($base_path !== '') {
                $home_paths[] = $base_path;
            }

            if (
                $request->getMethod() !== RequestMethodInterface::METHOD_GET ||
                !in_array($request->getUri()->getPath(), $home_paths, true) ||
                array_key_exists('route', $request->getQueryParams()) ||
                array_key_exists('route', $uri_query)
            ) {
                return response('', StatusCodeInterface::STATUS_NOT_FOUND);
            }
        }

        // Need the request to generate a route/error page.
        Registry::container()->set(ServerRequestInterface::class, $request);

        if ($request->getMethod() !== RequestMethodInterface::METHOD_GET) {
            throw new HttpNotFoundException();
        }

        return redirect(url: route(route_name: HomePage::class));
    }
}
