# Deploying robots.txt

The application generates rules from `CauchonBotPolicy` and
`resources/views/robots-txt.phtml`, using the installation's base URL, tree names,
and sitemap. Do not copy another installation's static robots.txt.

1. Open the generated robots route on your installation, normally
   `https://example.org/index.php?route=%2Frobots.txt`. With URL rewriting enabled,
   use your configured robots route.
2. Review the output for the correct domain, public tree names, sitemap, and
   exclusions. Confirm public records remain crawlable and named training agents
   remain excluded. Review which tree names appear before publishing the output.
3. If the web server does not serve that generated output at the domain-root
   `/robots.txt`, save the reviewed output there. A static root file can override
   the generated route. Installations in a subdirectory still need robots.txt at
   the domain root; coordinate rules with any other applications on that domain.
4. Verify the public `/robots.txt` response, including any CDN cache. Regenerate
   static copies after changes to the bot policy, template, domain, or tree names.

The root `/robots.txt` is intentionally ignored by Git. Keep site-specific copies
and deployment/rollback records in a private operations directory outside this
repository. The template and its tests belong in version control.

The wildcard group excludes expensive calendar/report/search/chart routes and
requests a 10-second crawl delay where supported. Named blocked-agent groups do
not inherit wildcard rules. `Applebot-Extended` is a robots-only usage control;
Applebot search remains allowed. Robots directives are advisory and do not
replace application privacy or authentication.

Run the generated-output checks with Python 3 and PHP 8.3 or newer on PATH:

```sh
python3 -m unittest discover -s tests/robots -v
```
