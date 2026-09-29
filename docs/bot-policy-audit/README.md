# Bad-bot policy audit and moderate-risk proposal

Date: 2026-09-28. This document records the rationale and research behind the fork policy.
The 40-entry proposal below has been implemented, along with the Facebot preview
exception and the robots-only Applebot-Extended opt-out. The effective HTTP list
now contains **1,308 entries**. Historical counts and the companion inventory
refer to the pre-change audit baseline, not the current runtime configuration.

## Recommendation

Adopt a public-reading policy: let people discover, read, cite, preview, and preserve public genealogy pages. Keep named training collectors and bulk commercial harvesting blocked. Allow site-wide auditing and operational integrations when deliberately used. Retire weak substring rules that penalize unrelated clients.

The first proposed batch removes **40 entries** from the effective HTTP list: **31 useful-service entries, six broad rules, and three stale/mismatched identifiers**. Separately add **Applebot-Extended as a robots.txt usage control** to preserve the intended training opt-out while keeping Apple search. This is more permissive than the current fork, without granting special privileges to allowed clients.

This is a public-content and server-load tradeoff. Extra reading, preview requests, and archived copies are acceptable under the fork's moderate-risk policy. Archival copies can remain after a page changes or is removed; blocked training agents and robots preferences cannot guarantee that public content never reaches a training dataset.

## Scope and confidence

The audit baseline was upstream 2.2.6 plus the initial fork policy. The 1,410 upstream entries contain **62** current exception keys. The fork adds one new effective token, MistralAI-Training, producing **1,349** effective blocked strings. The net reduction at that baseline was 61; this differs from the number of exception keys because the policy adds one token.

All 1,410 upstream entries and the one fork-added token were inventoried using the actual PHP arrays. All substring overlaps were computed. **351 entries received explicit group dispositions; 1,060 remain unclassified pending evidence.** Group assignment is not the same as a fully researched operator identity: this audit prioritizes plausible user-facing breakage, with primary documentation for major recommendations. Some recognizable service names remain provisional aliases.

This is a source and operator-documentation audit. List matching does not establish end-to-end access: installations must separately check their hosting/CDN rules, identity validation, robots.txt delivery, and privacy settings.

The companion [machine-readable inventory](bot-audit-inventory.json) contains every exact string, current state, disposition, evidence qualification, and overlapping blockers. It also includes a 15-sample policy simulation. It is a review artifact, not a configuration file.

## Groups and decisions

| Group | Recommendation | Reason and tradeoff |
| --- | --- | --- |
| Existing public search, previews, and user fetches | Preserve current split; review identity checks separately | Existing exceptions remain. Do not conflate search or user requests with model-training crawls. |
| Reading and accessibility | Allow selected services broadly | A person subscribing, saving, or listening has a direct benefit. Reader tools may refetch pages, cache full text, or support commercial features. |
| Citation and link checking | Allow selected citation tools | Public genealogy pages are useful research sources. Metadata extraction and reference maintenance should not be treated as inherently unwanted collection. |
| Shared-link previews | Allow selected additional services | A shared URL should produce a useful card. Some providers can also extract more than metadata. |
| Institutional preservation | Allow verified Archive-It and Arquivo agents | Strong fit with preserving genealogy. Copies may persist and crawl deeply; this is an intentional moderate-risk choice. |
| Public page diagnostics | Allow narrowly useful validators and inspection tools | Third parties may test a public page; an audit name is not an identity guarantee. |
| Uptime, certificates, infrastructure | Allow services actually configured | Useful operational dependencies, but provider/path/identity and deployment matter. A certificate label does not establish that PHP handles its challenge. |
| Bulk SEO, marketing, data brokers, scraping frameworks | Keep by default | Limited direct visitor benefit relative to crawl cost; optionally allow a commissioned audit or chosen integration. |
| Training and mixed collection | Keep existing named controls | Preserve current preference; some vendors combine training and other uses under one token. |
| Unknown/legacy tail | Retain pending evidence, not “confirmed bad” | Names alone are insufficient. Revisit when logs show blocked demand or operator evidence becomes available. |

## Batch A: 31 additional exceptions I recommend

These are **exact existing entries to subtract**, not new positive-whitelist rules. Do not skip subsequent checks just because a user-agent contains an allowed brand.

| Purpose | Exact entries | Evidence and confidence |
| --- | --- | --- |
| Reading, accessibility, actual browser | `Google-Read-Aloud`, `Feedly`, `Feedbin`, `Inoreader`, `inoreader.com`, `NewsBlur`, `FreshRSS`, `CommaFeed`, `FeedFlow`, `Instapaper`, `Konqueror` | Google explicitly documents user-triggered TTS; Feedly documents its fetcher. Other operators establish reader purpose; not every listed alias is a verified current HTTP UA. KDE identifies Konqueror as a browser, so blanket rejection is inappropriate. |
| Citation and reference maintenance | `Citoid`, `WMF Zotero Translation Server`, `ZoteroTranslationServer`, `IABot` | MediaWiki documents Citoid/Zotero metadata extraction. Internet Archive's own IABot project documents link-rot repair and configurable user-agent settings. Test complete UAs: `InternetArchiveBot` remains separately blocked, also by `ArchiveBot`. |
| Link previews | `BufferLinkPreviewBot`, `Iframely`, `Embedly`, `ClickUpLinkUnfurler` | Buffer and Iframely document exact agents and preview purpose. Embedly and ClickUp document useful product behavior; exact alias freshness is less certain. Embedly can extract full text too, a tradeoff accepted here. |
| Preservation | `Archive-It`, `archive.org_bot`, `Arquivo-web-crawler` | Exact names documented by their operators. No blanket exemption for every archive-sounding crawler. |
| Page diagnostics and owner verification | `Chrome-Lighthouse`, `W3 Validator Services`, `W3C_CSS_Validator`, `W3C-mobileOK`, `W3C_Unicorn`, `SecurityHeaders`, `SSL Labs`, `Google-Site-Verification` | Purpose is useful, but some W3C labels are historical. TLS checks may never reach PHP. Google's verification is owner-triggered; allowing the UA does not grant ownership or supply a verification token. |
| News product control | `Googlebot-News` | Google documents this as a robots-only product token, not a separate HTTP UA. Removing the restriction changes an exclusion preference; it does not promise News eligibility. |

Primary evidence:

- Reading: [Google Read Aloud](https://developers.google.com/search/docs/crawling-indexing/read-aloud-user-agent), [Feedly fetcher](https://feedly.com/fetcher.html), [Feedbin subscriptions](https://feedbin.com/help/how-to-subscribe/), [Inoreader](https://www.inoreader.com/blog/2024/11/getting-started-with-inoreader.html), [NewsBlur](https://www.newsblur.com/), [FreshRSS](https://github.com/FreshRSS/FreshRSS), [CommaFeed](https://github.com/Athou/commafeed), [FeedFlow](https://www.feedflow.dev/), [Instapaper](https://www.instapaper.com/), [KDE Konqueror](https://apps.kde.org/konqueror/).
- Citations: [Citoid](https://www.mediawiki.org/wiki/Citoid), [Zotero translators](https://www.zotero.org/support/dev/translators), [Internet Archive's IABot project](https://github.com/internetarchive/internetarchivebot).
- Previews: [Buffer](https://scraper.buffer.com/about/bots/link-preview-bot), [Iframely](https://iframely.com/docs/about), [Embedly API capabilities](https://docs.embed.ly/reference/embedly-api), [ClickUp previews](https://help.clickup.com/hc/en-us/articles/6305793042455-Embed-content-in-ClickUp).
- Preservation: [Archive-It crawler identity](https://support.archive-it.org/hc/en-us/articles/360017808671-Crawling-with-a-Custom-User-Agent), [Arquivo.pt](https://sobre.arquivo.pt/en/help/crawling-and-archiving-web-content/).
- Diagnostics: [Lighthouse](https://developer.chrome.com/docs/lighthouse/overview), [W3C validation services](https://validator.w3.org/services), [Security Headers](https://securityheaders.com/), [SSL Labs](https://www.ssllabs.com/ssltest/), [Google user-triggered fetchers](https://developers.google.com/crawling/docs/crawlers-fetchers/google-user-triggered-fetchers).

## Batch B: retire six broad substring rules

**`aa`, `008`, `dbot`, `Spider`, `Uptime`, `OpenAI`.**

My recommendation differs from a strict deny-by-default review: for the fork's moderate-risk policy, these are too weak to retain as permanent whole-header substring filters. Removing them permits some otherwise unidentified clients. It does **not** allow every crawler, every uptime vendor, or every OpenAI bot: their specific names continue to be evaluated.

- `Uptime` blocks the documented UptimeRobot UA although UptimeRobot is not itself listed. Named blocks such as `Better Uptime` and `SiteUptime` remain until separately allowed. [UptimeRobot documentation](https://help.uptimerobot.com/en/articles/11358489-what-is-the-uptimerobot-user-agent-string).
- `dbot` overlaps `Discordbot`, `feedbot`, `Refindbot`, and others. The current policy already needs a special exception for Discord. Specific remaining entries still apply.
- `Spider` overlaps 19 upstream names including itself. `Screaming Frog SEO Spider`, `ChatGLM-Spider`, and other individually listed crawlers remain blocked. Removing the broad entry also permits previously unlisted names containing this generic word.
- `aa` and `008` are extremely weak identifiers in an entire header; version strings, URLs, or other product names can contain them. This is a code-level false-positive risk, not a measured production incident. Named entries such as `CybaaBot` remain.
- Bare `OpenAI` is a vendor string, not one of the distinct crawler identities documented by OpenAI. Retain `GPTBot`; retain the existing SearchBot/User split. [OpenAI crawler documentation](https://developers.openai.com/api/docs/bots).

Other vague strings (`Observer`, `Operator`, `Titan`, `MSN`, `Jetty`, `DLC`) merit later review, but I am not recommending their automatic removal solely because they are short.

## Batch C: three stale or mismatched entries

Remove **`Feedfetcher-Google`, `Google-NotebookLM`, and `NotebookLM`** as housekeeping consistent with welcoming user-directed reading. Google currently documents `FeedFetcher-Google` with a capital second F; the current case-sensitive HTTP rule does not match it. Its current notebook fetcher is `Google-GeminiNotebook`, absent from this block list. Removing the two older notebook entries avoids overlapping historical restrictions, but should not be counted as fixing access for the current agent. [Google's current fetcher list](https://developers.google.com/crawling/docs/crawlers-fetchers/google-user-triggered-fetchers).

## Conditional groups worth revisiting

The inventory lists the exact members; these are priorities for a second batch, not automatic exceptions.

- **More readers/previews (50 entries):** examples include BazQux, theoldreader.com, Tiny Tiny RSS, Feeder, OpenRSS, Automattic Feed Fetcher, FlipboardRSS, Superfeedr, Blogtrottr, SummalyBot, SteamChat, and image proxies. Many are promising, but some provide broader aggregation or have ambiguous/obsolete identifiers. Confirm operator purpose before promoting the full group.
- **Monitoring (33):** Cloudflare health checks/traffic monitors, Datadog, Pingdom, Checkly, New Relic, and others. Allow whichever you use; a dedicated health endpoint is often a better integration than fetching arbitrary genealogy pages. Cloudflare documents its actual monitoring UAs. [Cloudflare crawler reference](https://developers.cloudflare.com/fundamentals/reference/cloudflare-site-crawling/).
- **Owner-commissioned audits (31):** AccessibleWebBot, Silktide, Siteimprove, Screaming Frog, AhrefsSiteAudit, GTmetrix, webpagetest, etc. Accessibility *audit crawlers* are different from a person's assistive reader and may traverse the whole site. [Accessible Web's crawler](https://accessibleweb.com/bot/).
- **Infrastructure (19):** CA validation, Cloudflare product features, hosted indexing integrations. HTTP certificate challenges might be answered before PHP; DNS/TLS validation never uses this middleware. An exception cannot create the required challenge endpoint. [Let's Encrypt challenge methods](https://letsencrypt.org/docs/challenge-types/), [Cloudflare hostname validation](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/domain-support/hostname-validation/zero-downtime-migration/).
- **Additional archives (18):** BnF, LOC, InternetArchiveBot, ia_archiver, ArchiveBot, Heritrix, and other aliases. Heritrix is software, not one institution. BnF is a particularly relevant optional inclusion for French genealogy, but collection/access practices differ. Do not infer operator from an archive-sounding string. [BnF crawler](https://www.bnf.fr/en/your-website-harvested-bnfs-robot), [LOC site-owner FAQ](https://www.loc.gov/programs/web-archiving/for-site-owners/frequently-asked-questions/).
- **Assistant/search ambiguities (15):** especially `meta-externalfetcher`, `Meta-ExternalFetcher`, and `meta-webindexer`. These should not automatically be treated as training. Meta's primary crawler documentation was inaccessible during this audit, so I am leaving a verification task instead of claiming its current policy. Other broad product labels similarly need actual UA evidence.

## What I recommend keeping blocked

Preserve all **14 existing training/data-collection strings**. This is the established policy boundary, not a claim that every agent exclusively trains models.

- **Amazonbot:** Amazon documents possible AI-training use, with separate `Amzn-SearchBot` and `Amzn-User` for search and user fetches. Those two aren't blocked by the current list, so no new exception is needed. [Amazon crawler documentation](https://developer.amazon.com/amazonbot).
- **CCBot:** Common Crawl is useful open research infrastructure, but a public reusable corpus is a broader redistribution choice than preserving selected pages. Keep the existing block unless open-corpus contribution is affirmatively desired. [Common Crawl](https://commoncrawl.org/faq).
- **GoogleOther:** Google describes general internal/product R&D, not a specific search benefit. **Google-Extended** is a robots usage control, affecting both Gemini training and grounding; retaining it means accepting that tradeoff. It is not a real separate HTTP agent. **Googlebot-News** is also robots-only, for a different purpose. [Google crawler reference](https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers).
- **Bulk commercial/harvesting tools:** keep AhrefsBot, SemrushBot, MJ12bot, DataForSEO, data brokers, ad intelligence, email harvesting, and general scraping frameworks by default. Their names are not proof of malicious behavior; they have less direct value to public-site readers. A commissioned audit can receive a deliberate exception.
- **Unsolicited scanners:** keep scanning agents blocked unless an assessment is requested. User-agent rejection is not a substitute for normal application security.

## One missing training control

Add **`Applebot-Extended: Disallow /` in the generated robots policy**, while retaining Applebot search access. Apple states that its crawl data may train foundation models and provides this separate usage opt-out; Extended does not crawl separately. This is a gap in the present “allow search, block training” claim. [Apple's documentation](https://support.apple.com/en-gb/119829).

Prefer separating robots-only usage controls from actual HTTP-blocking identities. The existing combined list can emit this instruction, but adding it must not cause a broad `Applebot` block. Confirm the live origin robots.txt actually contains it, since a static file may supersede the application's generated output.

## Matching and downstream access findings

1. **Exact exceptions are not positive allows.** The policy removes exact entries, then checks every other substring against the entire, case-sensitive UA. There are **57 current blocked entries whose own name also matches another active rule**. For example, removing `Google-NotebookLM` alone leaves `NotebookLM`; removing `InternetArchiveBot` alone leaves `ArchiveBot`.
2. **Allowing the list gate does not establish successful access.** Later middleware verifies selected Google and other identities by DNS/ASN. Its Google domains exclude the documented `gae.googleusercontent.com` family used by some user-triggered fetchers. Treat that as an integration mismatch to test, not a reason to trust every googleusercontent hostname. [Google fetcher infrastructure](https://developers.google.com/crawling/docs/crawlers-fetchers/google-user-triggered-fetchers).
3. **Cookie and route handling also matter.** The fork lets browser-looking clients receive the requested response and a test cookie together. Low-header, no-cookie requests can still be marked as robots and receive downstream route restrictions. Verify representative clients against the full application, not only the list matcher.
4. **HTTP and robots semantics differ.** This application uses case-sensitive substring matching; robots matching is case-insensitive and uses product tokens. Several upstream strings contain spaces, digits, or labels unsuitable as strict standard product tokens. Thus a shared string list does not guarantee identical behavior. [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309.html).
5. **Genealogy privacy is separate.** Keep existing authentication, living-person privacy, restricted records, and costly-route protections. Public-reader admission must not bypass them. The generated robots template already restricts many tree/report/search routes, but each installation must verify delivery at its domain root.

## Validation and suggested implementation order

- Runtime PHP extraction confirmed 1,410 upstream / 62 excluded / 14 mandatory tokens / 1,349 effective entries.
- The proposed 40 removals produce **1,309 HTTP block entries**, preserving every mandatory training token. A separate Applebot-Extended robots-only entry is not counted in that number. If kept in one combined array instead, its count would be 1,310.
- Fifteen sample UAs were evaluated against current and proposed strings; these are matcher simulations, not full application or live-provider tests. UptimeRobot changes from a `Uptime` hit to no list hit. Current FeedFetcher-Google already has no list hit. Screaming Frog remains blocked by its specific entry after generic Spider is removed; GPTBot stays blocked even when a UA also contains Feedly.
- Implement the reviewed batches in the fork policy, leaving upstream BAD_ROBOTS intact. Add full-UA tests covering both successful candidates and preserved training blocks. Then test representative public pages, generated/live robots.txt, identity validation, and cookie-challenge behavior.
- Record matched token, response reason, and request rate when operationally available, so future policy changes use observed demand. No new monitoring has been installed by this audit.

The stable-release workflow prepares updates for maintainer review; it does not deploy an installation. See [the fork guide](../../WEBTREES-LESS-RESTRICTIVE.md) for setup and [robots deployment guidance](../robots-deployment.md) for installation-specific checks.
