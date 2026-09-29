![Latest version](https://img.shields.io/github/v/release/fisharebest/webtrees?sort=semver)
![Licence](https://img.shields.io/github/license/fisharebest/webtrees)
[![Unit tests](https://github.com/fisharebest/webtrees/actions/workflows/phpunit.yaml/badge.svg)](https://github.com/fisharebest/webtrees/actions/workflows/phpunit.yaml)
[![codecov](https://codecov.io/gh/fisharebest/webtrees/branch/main/graph/badge.svg?token=zREQBP4GBs)](https://codecov.io/gh/fisharebest/webtrees)
[![Translation status](https://translate.webtrees.net/widgets/webtrees/-/webtrees-22/svg-badge.svg)](https://weblate.iet.open.ac.uk/projects/webtrees/webtrees-22)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fisharebest/webtrees/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/fisharebest/webtrees/?branch=main)
[![Code Climate](https://codeclimate.com/github/fisharebest/webtrees/badges/gpa.svg)](https://codeclimate.com/github/fisharebest/webtrees)
[![StyleCI](https://github.styleci.io/repos/11836349/shield?branch=main)](https://github.styleci.io/repos/11836349?branch=main)
# webtrees-seo-friendly

> **This is an independent fork.** `webtrees-seo-friendly` is maintained by
> Justin Cauchon and is not affiliated with or endorsed by the webtrees project.
> It follows stable releases of [fisharebest/webtrees](https://github.com/fisharebest/webtrees)
> and adjusts the bot policy to allow selected search engines, link previews,
> and user-requested fetches. Named AI-training agents remain blocked.
> See [WEBTREES-SEO-FRIENDLY.md](WEBTREES-SEO-FRIENDLY.md) for the policy and upstream review process.
> Report problems with this fork's bot policy here, not to the webtrees project.

The badges above describe the upstream project.

## Contents

* [License](#license)
* [Coding styles and standards](#coding-styles-and-standards)
* [Introduction](#introduction)
* [System requirements](#system-requirements)
* [Internet browser compatibility](#browser-compatibility)
* [Installation](#installation)
* [Upgrading](#upgrading)
* [Building and developing](#building-and-developing)
* [Gedcom (family tree) files](#gedcom-family-tree-files)
* [Security](#security)
* [Backup](#backup)
* [Restore from Backup](#restore-from-backup)

## License

* **webtrees: online genealogy**
* Copyright 2022 webtrees development team

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.

## Coding styles and standards

webtrees follows the [PHP Standards Recommendations](https://www.php-fig.org/psr).

* [PSR-1](https://www.php-fig.org/psr/psr-1) - Basic Coding Standard
* [PSR-2](https://www.php-fig.org/psr/psr-2) - Coding Style Guide
* [PSR-4](https://www.php-fig.org/psr/psr-4) - Autoloading Standard
* [PSR-6](https://www.php-fig.org/psr/psr-6) - Cache
* [PSR-7](https://www.php-fig.org/psr/psr-7) - HTTP Message Interface
* [PSR-11](https://www.php-fig.org/psr/psr-11) - Container Interface
* [PSR-12](https://www.php-fig.org/psr/psr-12) - Extended Coding Style Guide
* [PSR-15](https://www.php-fig.org/psr/psr-15) - HTTP Handlers
* [PSR-17](https://www.php-fig.org/psr/psr-17) - HTTP Factories

We do not currently use [PSR-3 (logging)](https://www.php-fig.org/psr/psr-3) - but we plan to do so in the future.

For JavaScript, we use [semistandard](https://github.com/standard/semistandard).

## Introduction

**webtrees** is the web's leading online collaborative genealogy application.

* It works from standard GEDCOM files, and is therefore compatible with every
major desktop application.
* It aims to to be efficient and effective by using the right combination of
third-party tools, design techniques and open standards.

**webtrees** allows you to view and edit your genealogy on your website. It has
full editing capabilities, full privacy functions, and supports imedia such as
photos and document images. As an online program, it fosters extended family
participation and good ancestral recording habits, as it simplifies the process
of collaborating with others working on your family lines. Your latest information
is always on your web site and available for others to see, defined by viewing
rules you set. For more information and to see working demos, visit
[webtrees.net](https://webtrees.net/).

**webtrees** is Open Source software that has been produced by people from many
countries freely donating their time and talents to the project. All service,
support, and future development is dependent on the time developers are willing
to donate to the project, often at the expense of work, recreation, and family.
Beyond the few donations received from users, developers receive no compensation
for the time they spend working on the project. There is also no outside source
of revenue to support the project. Please consider these circumstances when
making support requests and consider volunteering your own time and skills to make
the project even stronger and better.

## System requirements

To install **webtrees**, you need:

* A web server such as Apache, NGINX, or IIS. Pretty URLs require URL rewriting.
* A database supported by webtrees and its matching PHP PDO driver. MySQL is
  recommended; PostgreSQL, SQL Server, and SQLite are also supported.
* PHP 8.3 through 8.6 with the extensions required by [composer.json](composer.json),
  including ctype, curl, gd, iconv, intl, mbstring, PDO, session, and XML.
* Disk space for the application, dependencies, database, GEDCOM files, and media.
  Configure PHP memory and execution time for the size of your family trees.

See [installation and release guidance](docs/installation.md) for deployment and
build requirements.

## Browser compatibility
  
  **webtrees** is tested on recent versions of popular browsers such as Edge, Firefox,
  Chrome, and Safari.  Support for other browsers and older versions is on a case-by-case basis.

## Installation

Install a published **webtrees-seo-friendly** distribution ZIP attached to a
[release of this fork](https://github.com/Cauchon/webtrees-seo-friendly/releases). Extract its
`webtrees/` folder into the site directory, then open the site URL to start the
setup wizard. GitHub's automatically generated source ZIP and upstream webtrees
ZIPs are not fork distributions. If no fork release has been published, build a
distribution from a reviewed commit using the
[installation and release guide](docs/installation.md).

After setup, review tree privacy and the generated
[robots.txt guidance](docs/robots-deployment.md).

## Upgrading

Use a newer published distribution ZIP from this fork and follow the
[manual upgrade procedure](docs/installation.md#manual-upgrade). Back up the
database and `data/` directory, put the site in maintenance mode, merge the new
application files without replacing `data/`, then verify the site before
removing maintenance mode.

**Do not use the in-app automatic upgrade wizard for a fork installation.**
It checks the upstream webtrees update service and downloads an upstream ZIP,
which can replace this fork's code and bot policy. An upstream release or a
merged sync PR does not itself publish a fork release or update an installation.

## Building and developing

For development, install [Composer](https://getcomposer.org/) dependencies with
`composer install`. When changing JavaScript or CSS, run `npm ci` and
`npm run production`, then commit the generated assets with the source changes.

A maintainer builds an installable ZIP from a reviewed, clean, tagged commit with
`composer webtrees:build`. This requires Git, tar, zip, Composer, PHP, and the
extensions in `composer.json`; the build installs production PHP dependencies
and compiles translations. See the
[release procedure](docs/installation.md#building-and-publishing-a-fork-release)
before publishing an artifact. The release is a separate owner decision after
review and deployment checks.

## Gedcom (family tree) files

When you import a family tree (GEDCOM) file in **webtrees** the
data from the file is transferred to the database tables. The file itself 
remains in the **webtrees/data** folder and is no longer used or required
by **webtrees**. Any subsequent editing of the **webtrees** data
will not change this file

When or if you change your genealogy data outside of **webtrees**, it is not
necessary to delete your GEDCOM file or database from **webtrees** and start
over. Follow these steps to update a GEDCOM that has already been imported:

* Go to ``Control panel`` -> ``Manage family trees`` On the line relating to this particular family tree (GEDCOM)
  file (or a new one) select IMPORT.
* Take careful note of the media items option (_“If you have created media objects
  in **webtrees**, and have edited your data off-line using software that
  deletes media objects, then tick this box to merge the current media objects
  with the new GEDCOM.”_) In most cases you should leave this box **UNCHECKED**.
* Click “SAVE”. **webtrees** will validate the GEDCOM again before importing.
  During this process, **webtrees** copies your entire family tree (GEDCOM file)
  to a 'chunk' table within your database. Depending on the coding of your file,
  its file size and the capabilities of your server and the supporting software,
  this may take some time. **No progress bar will show while the data is being
  copied** and should you navigate away from this page, the process is suspended.
  It will start again when you return to the Family Tree management page.

## Security

**Security** in _webtrees_ means ensuring your site is safe from unwanted
intrusions, hacking, or access to data and configuration files. The developers
of _webtrees_ regard security as an extremely important part of its development
and have made every attempt to ensure your data is safe.

The area most at risk of intrusion would be the **/data** folder that contains your
config.ini.php file, and various temporary files. If you are concerned there
may be a risk there is a very simple test you can do: try to fetch the file 
config.ini.php by typing **``url_to_your_server/data/config.ini.php``** in your web
browser.

The most likely result is an “access denied” message like this:

    Forbidden

    You don't have permission to access /data/config.ini.php on this server.

This indicates that the protection built into **webtrees** is working, and no
further action is required.

In the unlikely event you do fetch the file (you will just see a semicolon),
then that protection is not working on your site and you should take some further
action.

If your server runs PHP in CGI mode, then change the permission of the **/data**
folder to 700 instead of 777. This will block access to the httpd process,
while still allowing access to PHP scripts.

This will work for perhaps 99% of all users. Only the remaining 1% should consider
the most complex solution, moving the **/data** folder out of accessible web
space. (**_Note:_** In many shared hosting environments this is not an option anyway.)

If you do find it necessary, following is an example of the process required:

If your home folder is something like **/home/username**,
and the root folder for your web site is **/home/username/public_html**,
and you have installed **webtrees** in the **public_html/webtrees** folder,
then you would create a new **data** folder in your home folder at the same
level as your public_html folder, such as **/home/username/private/data**,
and place your GEDCOM (family tree) file there.

Then change the **Data folder** setting on the ``Control panel`` ->
``Website`` -> ``Website preferences`` page from the default **data/** to the new
location **/home/username/private/data**

You will have **two** data directories:

* [path to webtrees]/data - just needs to contain config.ini.php
* /home/username/private/data - contains everything else

## Backup

Backups are good. Whatever problem you have, it can always be fixed from a good
backup.

To make a backup of **webtrees**, you need to make a copy of the following

1. The files in the *webtrees/data* folder.
2. The tables in the database. Freely available tools such as
   [phpMyAdmin](https://www.phpmyadmin.net) allow you to do this in one click. Alternatively, You can also make a backup running a mysqldump command (just replace the words *[localhost]*, *[username]*, *[password]* and *[databasename]* with your own):

    `mysqldump --host=[localhost] -u [username] -p[password] --databases [databasename] > dump_file.sql`

    Note that '*-p[password]*' goes together with no space in between.

Remember that most web hosting services do NOT backup your data, and this is
your responsibility.

## Restore from backup

To restore a backup on a new server:

1. Follow the steps in [Installation](#installation) to get a clean new installation.

2. Replace the *data* folder with backup copy.

3. Restore your mysql database using phpmyadmin or running the following command line on your database server using your mysqldumpfile (just replace the words *[username]*, *[password]* and *[databasename]* with your own):

    `mysql -u [username] -p[password] [database_name] < [dump_file.sql]`

4. Confirm the file *data/config.ini.php* contains to correct information to connect to the database and update it if needed.
