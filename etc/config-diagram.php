<?php

declare(strict_types=1);

const CONFIG_REPOSITORY = 'WyriHaximus/renovate-config';
const CONFIG_BRANCH = 'main';

const ENTRY_POINTS = [
    'default.json',
//    'docker-image.json',
//    'github-action.json',
//    'php-package-dev.json',
//    'php-package-next.json',
    'php-package.json',
//    'php-project-self-hosted-runner.json',
    'php-project.json',
];

const ENTRY_POINT_DESCRIPTIONS = [
    'default.json' => [
        'summary' => 'Alias for `php-package`',
        'description' => 'Alias for the `php-package` preset.',
    ],
    'docker-image.json' => [
        'summary' => 'Docker image repositories',
        'description' => 'For Docker image repositories.',
    ],
    'github-action.json' => [
        'summary' => 'GitHub Action repositories',
        'description' => 'For GitHub Action repositories.',
    ],
    'php-package-dev.json' => [
        'summary' => 'PHP packages with dev-oriented bump strategy',
        'description' => 'For PHP packages where dev dependencies should use `bump` instead of `widen`.',
    ],
    'php-package-next.json' => [
        'summary' => 'PHP packages targeting next-version in-range updates',
        'description' => 'For PHP packages experimenting with next-version updates using in-range-only Composer updates.',
    ],
    'php-package.json' => [
        'summary' => 'PHP packages on public repositories',
        'description' => 'For PHP packages on public repositories. Uses `widen` for Composer dependencies.',
    ],
    'php-project-self-hosted-runner.json' => [
        'summary' => 'PHP projects using self-hosted runners, with dev PHP rules',
        'description' => 'For PHP projects running on self-hosted runners, using dev PHP rules without top-level Composer bump.',
    ],
    'php-project.json' => [
        'summary' => 'PHP projects on private repositories with limited Actions credits',
        'description' => 'For PHP projects on private repositories with limited Actions credits. Limits PR creation rate and uses `bump`.',
    ],
];

/** @return list<array{file: string, name: string, summary: string, description: string}> */
function buildEntryPointCatalog(): array
{
    $catalog = [];

    foreach (ENTRY_POINTS as $file) {
        if (!isset(ENTRY_POINT_DESCRIPTIONS[$file])) {
            throw new RuntimeException(sprintf('Missing description for entry point: %s', $file));
        }

        $catalog[] = [
            'file' => $file,
            'name' => presetDisplayName($file),
            'summary' => ENTRY_POINT_DESCRIPTIONS[$file]['summary'],
            'description' => ENTRY_POINT_DESCRIPTIONS[$file]['description'],
        ];
    }

    return $catalog;
}

/** @return list<string> */
function architectureAnchorPresets(): array
{
    return array_values(array_filter(
        ENTRY_POINTS,
        static fn (string $file): bool => $file !== 'default.json',
    ));
}

/** @return array<string, string> */
function buildPresetDiagrams(string $repoRoot): array
{
    $edges = collectPresetGraph($repoRoot, ENTRY_POINTS);
    $diagrams = [];

    foreach (ENTRY_POINTS as $entryPoint) {
        $mermaid = buildMermaidDiagram($edges, $entryPoint);
        $diagrams[$entryPoint] = <<<MARKDOWN
##### Preset connections

```mermaid
{$mermaid}
```

MARKDOWN;
    }

    return $diagrams;
}

function buildArchitectureDiagram(string $repoRoot): string
{
    $lines = ['flowchart TB'];
    $consumer = 'consumer["Consumer renovate.json"]';
    $lines[] = sprintf('  %s', $consumer);

    foreach (ENTRY_POINTS as $entryPoint) {
        $slug = mermaidSlug($entryPoint);
        $label = presetDisplayName($entryPoint);
        $lines[] = sprintf('  consumer --> %s["%s"]', $slug, $label);
        $lines[] = sprintf('  click %s "%s" _blank', $slug, presetFileUrl($entryPoint));
    }

    $layers = [
        'internalLayer["internal/ presets"]',
        'composerLayer["composer/ presets"]',
        'githubActionsLayer["github-actions/ presets"]',
        'externalLayer("Renovate built-in presets")',
    ];

    foreach ($layers as $layer) {
        $lines[] = sprintf('  %s', $layer);
    }

    foreach (architectureAnchorPresets() as $anchor) {
        $slug = mermaidSlug($anchor);
        $lines[] = sprintf('  %s --> internalLayer', $slug);
        $lines[] = sprintf('  %s --> composerLayer', $slug);
        $lines[] = sprintf('  %s --> githubActionsLayer', $slug);
    }

    $lines[] = '  internalLayer --> externalLayer';

    return implode("\n", $lines);
}

/** @return list<array{from: string, to: string, entryPoint: string, kind: string}> */
function collectPresetGraph(string $repoRoot, array $entryPoints): array
{
    $edges = [];

    foreach ($entryPoints as $entryPoint) {
        $visited = [];
        $queue = [$entryPoint];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;
            $config = readPreset($repoRoot, $current);

            if ($config === null) {
                continue;
            }

            foreach (parseExtendsFromConfig($config) as $reference) {
                $resolved = resolvePresetReference($reference);

                if ($resolved === null) {
                    continue;
                }

                if ($resolved['type'] === 'local') {
                    $target = $resolved['path'];
                    $edges[] = ['from' => $current, 'to' => $target, 'entryPoint' => $entryPoint, 'kind' => 'local'];

                    if (!isset($visited[$target])) {
                        $queue[] = $target;
                    }

                    continue;
                }

                $edges[] = ['from' => $current, 'to' => $resolved['name'], 'entryPoint' => $entryPoint, 'kind' => 'external'];
            }
        }
    }

    usort($edges, static fn (array $a, array $b): int => [$a['entryPoint'], $a['kind'], $a['from'], $a['to']] <=> [$b['entryPoint'], $b['kind'], $b['from'], $b['to']]);

    return $edges;
}

/** @return list<string> */
function parseExtendsFromConfig(array $config): array
{
    $extends = [];

    if (isset($config['extends']) && is_array($config['extends'])) {
        $extends = array_merge($extends, $config['extends']);
    }

    foreach ($config['packageRules'] ?? [] as $rule) {
        if (!is_array($rule) || !isset($rule['extends']) || !is_array($rule['extends'])) {
            continue;
        }

        $extends = array_merge($extends, $rule['extends']);
    }

    sort($extends);

    return array_values(array_unique($extends));
}

/** @return array{type: string, path?: string, name?: string}|null */
function resolvePresetReference(string $reference): ?array
{
    if (preg_match('#^github>wyrihaximus/renovate-config:(.+)$#i', $reference, $matches) === 1) {
        return ['type' => 'local', 'path' => $matches[1] . '.json'];
    }

    if (preg_match('#^github>wyrihaximus/renovate-config//(.+)$#i', $reference, $matches) === 1) {
        return ['type' => 'local', 'path' => $matches[1] . '.json'];
    }

    if (str_starts_with($reference, 'github>') || str_starts_with($reference, 'local>')) {
        return null;
    }

    return ['type' => 'external', 'name' => $reference];
}

function readPreset(string $repoRoot, string $relativePath): ?array
{
    $path = $repoRoot . '/' . $relativePath;

    if (!is_file($path)) {
        return null;
    }

    /** @var array<string, mixed> */
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

/** @param list<array{from: string, to: string, entryPoint: string, kind: string}> $edges */
function buildMermaidDiagram(array $edges, string $entryPoint): string
{
    $lines = ['flowchart TB'];
    $localLinks = [];
    $externalLinks = [];
    $externalIds = [];
    $nodeLinks = [];
    $prefix = mermaidSlug($entryPoint);
    $edgeIndex = 0;

    foreach ($edges as $edge) {
        if ($edge['entryPoint'] !== $entryPoint) {
            continue;
        }

        $from = $prefix . '_' . mermaidSlug($edge['from']);
        $nodeLinks[$from] ??= presetFileUrl($edge['from']);

        if ($edge['kind'] === 'external') {
            $externalIds[$edge['to']] ??= $prefix . '_e' . count($externalIds);
            $lines[] = sprintf('  %s["%s"] --> %s("%s")', $from, $edge['from'], $externalIds[$edge['to']], $edge['to']);
            $externalLinks[] = $edgeIndex;
        } else {
            $to = $prefix . '_' . mermaidSlug($edge['to']);
            $nodeLinks[$to] ??= presetFileUrl($edge['to']);
            $lines[] = sprintf('  %s["%s"] --> %s["%s"]', $from, $edge['from'], $to, $edge['to']);
            $localLinks[] = $edgeIndex;
        }

        $edgeIndex++;
    }

    foreach ([[$localLinks, '#22c55e'], [$externalLinks, '#2563eb']] as [$indexes, $color]) {
        if ($indexes !== []) {
            $lines[] = sprintf('  linkStyle %s stroke:%s,stroke-width:2px', implode(',', $indexes), $color);
        }
    }

    ksort($nodeLinks);

    foreach ($nodeLinks as $nodeId => $url) {
        if ($url !== null) {
            $lines[] = sprintf('  click %s "%s" _blank', $nodeId, $url);
        }
    }

    return implode("\n", $lines);
}

function presetFileUrl(string $relativePath): string
{
    return sprintf(
        'https://github.com/%s/blob/%s/%s',
        CONFIG_REPOSITORY,
        CONFIG_BRANCH,
        str_replace('%2F', '/', rawurlencode($relativePath)),
    );
}

function presetDisplayName(string $relativePath): string
{
    return str_ends_with($relativePath, '.json')
        ? substr($relativePath, 0, -5)
        : $relativePath;
}

function mermaidSlug(string $name): string
{
    return str_replace(['/', '.', '-'], '_', pathinfo($name, PATHINFO_FILENAME));
}

/** @return list<string> */
function discoverPresetFiles(string $repoRoot, string $directory): array
{
    $files = [];
    $path = $repoRoot . '/' . $directory;

    if (!is_dir($path)) {
        return $files;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if (!str_ends_with($entry, '.json')) {
            continue;
        }

        $files[] = $directory . '/' . $entry;
    }

    sort($files);

    return $files;
}

/** @return list<array<string, string>> */
function buildComposerPresetCatalog(string $repoRoot): array
{
    $catalog = [];

    foreach (discoverPresetFiles($repoRoot, 'composer') as $file) {
        $config = readPreset($repoRoot, $file);
        if ($config === null) {
            continue;
        }

        $catalog[] = [
            'name' => basename($file, '.json'),
            'groupName' => (string) ($config['groupName'] ?? ''),
            'managers' => implode(', ', $config['managers'] ?? []),
            'rangeStrategy' => (string) ($config['rangeStrategy'] ?? ''),
            'match' => summarizeMatchRules($config),
            'enabled' => summarizeEnabled($config),
            'prPriority' => isset($config['prPriority']) ? (string) $config['prPriority'] : '',
        ];
    }

    return $catalog;
}

/** @return list<array<string, string>> */
function buildInternalPresetCatalog(string $repoRoot): array
{
    $catalog = [];

    foreach (discoverPresetFiles($repoRoot, 'internal') as $file) {
        $config = readPreset($repoRoot, $file);
        if ($config === null) {
            continue;
        }

        $catalog[] = [
            'name' => basename($file, '.json'),
            'summary' => summarizeInternalPreset($config),
        ];
    }

    return $catalog;
}

/** @return list<array<string, string>> */
function buildGithubActionsPresetCatalog(string $repoRoot): array
{
    $catalog = [];

    foreach (discoverPresetFiles($repoRoot, 'github-actions') as $file) {
        $config = readPreset($repoRoot, $file);
        if ($config === null) {
            continue;
        }

        $catalog[] = [
            'name' => basename($file, '.json'),
            'managers' => implode(', ', $config['managers'] ?? []),
            'rangeStrategy' => (string) ($config['rangeStrategy'] ?? ''),
            'prPriority' => isset($config['prPriority']) ? (string) $config['prPriority'] : '',
        ];
    }

    return $catalog;
}

/** @param array<string, mixed> $config */
function summarizeMatchRules(array $config): string
{
    $parts = [];

    if (isset($config['matchPackagePatterns']) && is_array($config['matchPackagePatterns'])) {
        $parts[] = 'patterns: ' . implode(', ', $config['matchPackagePatterns']);
    }

    if (isset($config['matchPackagePrefixes']) && is_array($config['matchPackagePrefixes'])) {
        $parts[] = 'prefixes: ' . implode(', ', $config['matchPackagePrefixes']);
    }

    if (isset($config['matchDepNames']) && is_array($config['matchDepNames'])) {
        $parts[] = 'dep names: ' . implode(', ', $config['matchDepNames']);
    }

    return implode('; ', $parts);
}

/** @param array<string, mixed> $config */
function summarizeEnabled(array $config): string
{
    if (!array_key_exists('enabled', $config)) {
        return '';
    }

    return $config['enabled'] ? 'true' : 'false';
}

/** @param array<string, mixed> $config */
function summarizeInternalPreset(array $config): string
{
    $parts = [];

    if (isset($config['timezone'])) {
        $parts[] = 'timezone ' . $config['timezone'];
    }

    if (isset($config['prConcurrentLimit'])) {
        $parts[] = 'prConcurrentLimit ' . $config['prConcurrentLimit'];
    }

    if (isset($config['addLabels']) && is_array($config['addLabels'])) {
        $parts[] = 'labels: ' . implode(', ', $config['addLabels']);
    }

    if (isset($config['extends']) && is_array($config['extends'])) {
        $parts[] = count($config['extends']) . ' extends';
    }

    if (isset($config['packageRules']) && is_array($config['packageRules'])) {
        $parts[] = count($config['packageRules']) . ' packageRules';
    }

    return implode('; ', $parts);
}

function buildArchitectureDiagramMarkdown(string $repoRoot): string
{
    return sprintf(
        "```mermaid\n%s\n```",
        buildArchitectureDiagram($repoRoot),
    );
}

function buildEntryPointsTableMarkdown(): string
{
    $lines = [
        '| Preset | Usage |',
        '|--------|-------|',
    ];

    foreach (buildEntryPointCatalog() as $entryPoint) {
        $lines[] = sprintf('| `%s` | %s |', $entryPoint['name'], $entryPoint['summary']);
    }

    return implode("\n", $lines);
}

function buildInternalPresetsSummaryTableMarkdown(string $repoRoot): string
{
    $lines = [
        '| Name | Summary |',
        '|------|---------|',
    ];

    foreach (buildInternalPresetCatalog($repoRoot) as $preset) {
        $lines[] = sprintf('| %s | %s |', $preset['name'], $preset['summary']);
    }

    return implode("\n", $lines);
}

function buildComposerPresetsTableMarkdown(string $repoRoot): string
{
    $lines = [
        '| Name | Group | Managers | Range strategy | Match | Enabled | Priority |',
        '|------|-------|----------|----------------|-------|---------|----------|',
    ];

    foreach (buildComposerPresetCatalog($repoRoot) as $preset) {
        $lines[] = sprintf(
            '| %s | %s | %s | %s | %s | %s | %s |',
            $preset['name'],
            $preset['groupName'],
            $preset['managers'],
            $preset['rangeStrategy'],
            $preset['match'],
            $preset['enabled'],
            $preset['prPriority'],
        );
    }

    return implode("\n", $lines);
}

function buildGithubActionsPresetsTableMarkdown(string $repoRoot): string
{
    $lines = [
        '| Name | Managers | Range strategy | Priority |',
        '|------|----------|----------------|----------|',
    ];

    foreach (buildGithubActionsPresetCatalog($repoRoot) as $preset) {
        $lines[] = sprintf(
            '| %s | %s | %s | %s |',
            $preset['name'],
            $preset['managers'],
            $preset['rangeStrategy'],
            $preset['prPriority'],
        );
    }

    return implode("\n", $lines);
}

/** @return list<array{name: string, description: string, example: string, diagram: string}> */
function buildUsageSections(string $repoRoot): array
{
    $diagrams = buildPresetDiagrams($repoRoot);
    $sections = [];

    foreach (buildEntryPointCatalog() as $entryPoint) {
        $sections[] = [
            'name' => $entryPoint['name'],
            'description' => $entryPoint['description'],
            'example' => sprintf(
                <<<'JSON'
```json
{
  "$schema": "https://docs.renovatebot.com/renovate-schema.json",
  "extends": [
    "github>WyriHaximus/renovate-config:%s"
  ]
}
```
JSON,
                $entryPoint['name'],
            ),
            'diagram' => $diagrams[$entryPoint['file']],
        ];
    }

    return $sections;
}
