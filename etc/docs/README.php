<?php

declare(strict_types=1);

use DTL\Docbot\Article\Article;
use DTL\Docbot\Extension\Core\Block\SectionBlock;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../config-diagram.php';

$repoRoot = dirname(__DIR__, 2);

/** @var list<SectionBlock> $usageSections */
$usageSections = [];

foreach (buildUsageSections($repoRoot) as $usageSection) {
    $usageSections[] = new SectionBlock($usageSection['name'], [
        $usageSection['description'],
        $usageSection['example'],
        $usageSection['diagram'],
    ]);
}

return Article::create('README', 'renovate-config', [
    <<<'TEXT'
    Shared [Renovate](https://github.com/renovatebot/renovate) configuration for WyriHaximus repositories. Presets are
    composed from reusable building blocks in `internal/`, `composer/`, and `github-actions/`.

    Preset connection diagrams and catalog tables in this README are generated automatically from the JSON files when
    running `make generate`.
    TEXT,
    new SectionBlock('Opinionated choices', [
        new SectionBlock('Base configuration', [
            <<<'TEXT'
            [`internal/base.json`](internal/base.json) sets the defaults used by all presets:

            * `Europe/UTC` timezone
            * OSV vulnerability alerts enabled
            * GitHub Action digest pinning helpers
            * Dependency labels from [`internal/labels.json`](internal/labels.json)
            * Tool constraint updates for `php` and `composer` disabled via [`internal/do-not-update-tool-constraints.json`](internal/do-not-update-tool-constraints.json)
            TEXT,
        ]),
        new SectionBlock('Package vs project throttling', [
            <<<'TEXT'
            [`internal/package.json`](internal/package.json) removes PR limits for public packages (`:prHourlyLimitNone`,
            `:prConcurrentLimitNone`). [`internal/project.json`](internal/project.json) limits projects to one PR per hour and
            three concurrent PRs to reduce rebasing churn across many open PRs.
            TEXT,
        ]),
        new SectionBlock('Composer range strategies', [
            <<<'TEXT'
            Public PHP packages use `widen` via [`composer/widen.json`](composer/widen.json). Projects and dev-oriented presets use
            `bump` via [`composer/bump.json`](composer/bump.json). The `php-package-next` preset uses `in-range-only` for
            next-version experimentation via [`composer/in-range.json`](composer/in-range.json).
            TEXT,
        ]),
        new SectionBlock('PHP constraint pinning', [
            <<<'TEXT'
            [`composer/do-not-update-php.json`](composer/do-not-update-php.json) prevents Renovate from bumping the `php`
            constraint in `composer.json`.
            TEXT,
        ]),
        new SectionBlock('Labels and grouping', [
            <<<'TEXT'
            [`internal/labels.json`](internal/labels.json) adds dependency labels by update type. PHP presets add a `PHP 🐘` label.
            GitHub Actions presets add a `CI 🚧` label and group Actions updates via [`github-actions/bump.json`](github-actions/bump.json).
            TEXT,
        ]),
    ]),
    new SectionBlock('Architecture', [
        <<<'TEXT'
        Consumer repositories extend a top-level preset, which composes internal, composer, and GitHub Actions building blocks.
        Those building blocks may in turn extend Renovate built-in presets such as `config:base` or `group:phpstan`.
        TEXT,
        buildArchitectureDiagramMarkdown($repoRoot),
        'Green edges in preset diagrams below show local preset references. Blue edges show Renovate built-in presets.',
    ]),
    new SectionBlock('Top-level presets', [
        <<<'TEXT'
        These presets are the documented entry points. Pick one and extend it from your repository's `renovate.json`.
        TEXT,
        buildEntryPointsTableMarkdown(),
    ]),
    new SectionBlock('Usage', array_merge(
        [
            'Preset connection diagrams are generated automatically from `extends` references when running `make generate`.',
        ],
        $usageSections,
    )),
    new SectionBlock('Building blocks', [
        <<<'TEXT'
        These lower-level presets compose the top-level presets above. Use them directly when you need a more advanced setup.
        TEXT,
        new SectionBlock('Internal presets', [
            buildInternalPresetsSummaryTableMarkdown($repoRoot),
            <<<'TEXT'
            | Name | Usage |
            |------|-------|
            | `base` | Scheduling, Europe/UTC timezone, and base extends |
            | `package` | No limits on how often or how many PRs are created |
            | `project` | Limits to one PR per hour and three concurrent PRs |
            | `php` | PHP repositories with labels, package rules, and grouped updates |
            | `php-dev` | PHP dev preset with bump strategy for dev dependencies |
            | `php-next` | PHP next-version preset with in-range updates |
            | `github-actions` | GitHub Actions grouping and CI label |

            Reference internal presets with the path syntax:

            ```json
            {
              "extends": [
                "github>WyriHaximus/renovate-config//internal/base"
              ]
            }
            ```
            TEXT,
        ]),
        new SectionBlock('Composer presets', [
            buildComposerPresetsTableMarkdown($repoRoot),
            <<<'TEXT'
            Reference composer presets with the path syntax:

            ```json
            {
              "extends": [
                "github>WyriHaximus/renovate-config//composer/widen"
              ]
            }
            ```
            TEXT,
        ]),
        new SectionBlock('GitHub Actions presets', [
            buildGithubActionsPresetsTableMarkdown($repoRoot),
            <<<'TEXT'
            Reference GitHub Actions presets with the path syntax:

            ```json
            {
              "extends": [
                "github>WyriHaximus/renovate-config//github-actions/bump"
              ]
            }
            ```
            TEXT,
        ]),
    ]),
    new SectionBlock('Generating documentation', [
        <<<'TEXT'
        ```bash
        make generate
        ```

        This installs PHP dependencies and regenerates `README.md` from [`etc/docs/README.php`](etc/docs/README.php) and the
        preset JSON files using [docbot](https://github.com/dantleech/docbot).
        TEXT,
    ]),
    new SectionBlock('Local CI', [
        <<<'TEXT'
        ```bash
        make
        ```

        This runs the same checks as CI:

        * `make renovate-config-validator` — validate all top-level preset JSON files
        * `make ensure-readme-is-up-to-date` — regenerate `README.md` and fail if it differs from the committed copy
        TEXT,
    ]),
    new SectionBlock('License', [
        <<<'TEXT'
        Copyright 2026 [Cees-Jan Kiewiet](http://wyrihaximus.net/)

        Permission is hereby granted, free of charge, to any person
        obtaining a copy of this software and associated documentation
        files (the "Software"), to deal in the Software without
        restriction, including without limitation the rights to use,
        copy, modify, merge, publish, distribute, sublicense, and/or sell
        copies of the Software, and to permit persons to whom the
        Software is furnished to do so, subject to the following
        conditions:

        The above copyright notice and this permission notice shall be
        included in all copies or substantial portions of the Software.

        THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
        EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
        OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
        NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
        HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
        WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
        FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
        OTHER DEALINGS IN THE SOFTWARE.
        TEXT,
    ]),
]);
