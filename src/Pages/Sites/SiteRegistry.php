<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Sites;

use RuntimeException;
use Systopic\System\Config\Config;
use Systopic\System\Config\InstanceFiles;
use Systopic\Db\Scope;
use Systopic\System\Queries\NodeChildren\NodeChildren;
use Systopic\Cms\Queries\Sites\Sites;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;
use Systopic\Cms\Tables\Sites\Model as Site;

/**
 * The sites of the installation, from `cms_sites`.
 *
 * A site is a node of type 'site' on the topmost level of the page tree, with
 * a row here carrying its name (the domain) and its meta. The default site
 * has a row without a node: its pages are the topmost pages themselves.
 *
 * Read once per request, right after the database connection, and handed to
 * `Config::useSites()` — the instance files say WHERE a site answers, this
 * table says WHICH sites there are. Written by the pages panel
 * (cms/pages: add menu, site editor in main).
 */
final class SiteRegistry
{
    public const NODE_TYPE = 'site';

    /** Subfolders a new site gets below sites/<name>/templates/. */
    private const TEMPLATE_FOLDERS = [
        'document'   => 'document, head, head.loader, body — wird auf den Site-Root angewendet und ersetzt templates/document/ für diese Site.',
        'components' => 'navi, header, footer, galerie, kontaktformular … — ersetzt gleichnamige Dateien aus templates/components/ für diese Site.',
        'pages'      => 'Seiten-Templates (<nr>_<name>/main.tpl.php) nur für diese Site; ein gleichnamiges Template ersetzt das aus templates/pages/.',
        'layout'     => 'grid, rows, columns — in Planung, wird noch nicht geladen.',
    ];

    /** @return list<Site> every site, the default first */
    public static function all(Scope $scope): array
    {
        return Sites::all($scope);
    }

    public static function byId(Scope $scope, int $id): ?Site
    {
        foreach (self::all($scope) as $site) {
            if ((int) $site->id === $id) {
                return $site;
            }
        }
        return null;
    }

    /** The site whose node this is. */
    public static function byNodeId(Scope $scope, int $nodeId): ?Site
    {
        foreach (self::all($scope) as $site) {
            if ($site->relationId('node') === $nodeId) {
                return $site;
            }
        }
        return null;
    }

    /** The site without a node — null while there is none. */
    public static function defaultSite(Scope $scope): ?Site
    {
        foreach (self::all($scope) as $site) {
            if ($site->isDefault) {
                return $site;
            }
        }
        return null;
    }

    /** The name of the site whose node this is, null when it is none. */
    public static function nameOfNode(Scope $scope, int $nodeId): ?string
    {
        foreach (self::all($scope) as $site) {
            if ($site->relationId('node') === $nodeId) {
                return $site->name;
            }
        }
        return null;
    }

    public static function byName(Scope $scope, string $name): ?Site
    {
        foreach (self::all($scope) as $site) {
            if ($site->name === $name) {
                return $site;
            }
        }
        return null;
    }

    /**
     * The sites in the shape Config\Sites reads: name => root, default, meta.
     * Empty when the table has no rows — then config.json's `sites` stay.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function configData(Scope $scope): array
    {
        $out = [];
        foreach (self::all($scope) as $site) {
            $out[$site->name] = [
                'root'    => $site->relationId('node'),
                'default' => $site->isDefault,
                'meta'    => $site->metaData,
                'aliases' => $site->aliasList,
            ];
        }
        return $out;
    }

    /**
     * Loads the sites into the config and picks the one the request is for.
     * Called by loader.php once the database is connected.
     */
    public static function boot(): void
    {
        $config = Config::current();
        if ($config === null) {
            return;
        }
        try {
            if (\Systopic\Db\Connection::isOpen()) {
                $data = self::configData(Scope::default());
                if ($data !== []) {
                    $config->useSites($data);
                }
            }
        } catch (\Throwable $e) {
            // a broken sites table must not take the site down: config.json's
            // sites (or the implicit single site) stay in place
            error_log('SiteRegistry::boot: ' . $e->getMessage());
        }
        // DEV ("linkSites": true in the instance file): the missing junction
        // per site, e.g. projects/beautiful-technology.de → projects/systopic.net
        $config->linkSites((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), \fs::toNative((string) \fs::$projectRoot));
        $config->resolveSite((string) \http::$host, (string) \http::$root);
        self::redirectAlias($config);
    }

    // ------------------------------------------------------------------ write

    /** A domain with TLD, lower case: 'beautiful-technology.de'. */
    public static function validName(string $name): bool
    {
        return (bool) preg_match('~^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$~', $name);
    }

    /**
     * A new site: the node of type 'site' at the end of the topmost level,
     * its row, and its folders below sites/<name>/.
     *
     * The first site of an installation becomes the default site (no node).
     */
    /**
     * @param list<string>|null $log receives what was written to the instance files
     */
    public static function create(Scope $scope, string $name, ?string $projectRoot = null, ?array &$log = null): Site
    {
        $name = strtolower(trim($name));
        if (!self::validName($name)) {
            throw new RuntimeException("'$name' ist kein Domainname mit TLD (z. B. beautiful-technology.de).");
        }
        if (self::byName($scope, $name) !== null) {
            throw new RuntimeException("Die Site '$name' gibt es schon.");
        }

        $site = new Site();
        $site->name = $name;
        $site->meta = new Text('{}');

        if (self::all($scope) === []) {
            $scope->save($site); // the first one is the default site: no node
        } else {
            $node = new Node();
            $node->type = self::NODE_TYPE;
            $uow = $scope->uow();
            foreach (NodeOperator::placed($scope, $node, null, PHP_INT_MAX) as $changed) {
                $uow->persist($changed);
            }
            $uow->commit();
            $site->node = $node;
            $scope->save($site);
        }

        self::createFolders($name, $projectRoot);
        $known = array_map(static fn(Site $s) => $s->name, self::all($scope));
        $log = InstanceFiles::addSite(self::configDir($projectRoot), $name, array_values(array_diff($known, [$name])));
        return $site;
    }

    /**
     * Removes a site — only while no page hangs below it, and never the
     * default site. The folder sites/<name>/ stays: it is code.
     */
    /** @return list<string> what was written to the instance files */
    public static function remove(Scope $scope, Site $site): array
    {
        $nodeId = $site->relationId('node');
        if ($nodeId === null) {
            throw new RuntimeException('Die Default-Site kann nicht entfernt werden.');
        }
        // every child counts, trashed ones too: the site node cannot go while
        // anything still points at it
        $children = count(NodeChildren::of($scope, $nodeId));
        if ($children > 0) {
            throw new RuntimeException("Unter '{$site->name}' hängen noch $children Seiten — erst verschieben oder löschen.");
        }
        $node = $site->node;
        $scope->remove($site);   // first the row that points at the node
        if ($node !== null && $node->type === self::NODE_TYPE) {
            $scope->remove($node);
        }
        return InstanceFiles::removeSite(self::configDir(), $site->name);
    }

    /** <project>/config — where the instance files are. */
    private static function configDir(?string $projectRoot = null): string
    {
        return rtrim(str_replace('\\', '/', \fs::toNative($projectRoot ?? (string) \fs::$projectRoot)), '/') . '/config';
    }

    /**
     * Gives a site another domain: the row, the folder sites/<old>/ and the
     * directory links of this machine (/projects/<old>/ → the project).
     *
     * Only links are moved, never a real folder: on DEV the default site's
     * path usually IS the project folder, that one stays. Everything is
     * checked before the first change.
     *
     * @return list<string> what was done, for the panel
     */
    public static function rename(Scope $scope, Site $site, string $newName, ?string $projectRoot = null): array
    {
        $newName = strtolower(trim($newName));
        $oldName = $site->name;
        if ($newName === $oldName) {
            return [];
        }
        if (!self::validName($newName)) {
            throw new RuntimeException("'$newName' ist kein Domainname mit TLD (z. B. beautiful-technology.de).");
        }
        if (self::byName($scope, $newName) !== null) {
            throw new RuntimeException("Die Site '$newName' gibt es schon.");
        }

        $root = rtrim(str_replace('\\', '/', $projectRoot ?? (string) \fs::$projectRoot), '/');
        $oldDir = \fs::toNative("$root/sites/$oldName");
        $newDir = \fs::toNative("$root/sites/$newName");
        if (is_dir($newDir)) {
            throw new RuntimeException("Der Ordner sites/$newName/ gibt es schon.");
        }

        // the links of this machine: the site's path entries now, and the
        // same with the name swapped — what InstanceFiles writes below
        $config = Config::current();
        $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        $target = \fs::toNative($root);
        $links = [];
        $oldEntries = $config?->sites->byName($oldName)?->entries ?? [];
        if ($documentRoot !== '' && $oldEntries !== []) {
            $newEntries = array_map(static fn(string $e) => InstanceFiles::renamePath($e, $oldName, $newName), $oldEntries);
            $from = \Systopic\System\Config\SiteLinks::pathsOf($oldEntries, $documentRoot);
            $to = \Systopic\System\Config\SiteLinks::pathsOf($newEntries, $documentRoot);
            foreach ($from as $i => $path) {
                if (isset($to[$i]) && $to[$i] !== $path) {
                    $links[$path] = $to[$i];
                }
            }
        }

        $log = [];
        if (is_dir($oldDir)) {
            if (!@rename($oldDir, $newDir)) {
                throw new RuntimeException("Ordner sites/$oldName/ konnte nicht umbenannt werden.");
            }
            $log[] = "Ordner sites/$oldName/ → sites/$newName/";
        }
        foreach ($links as $from => $to) {
            if (\Systopic\System\Config\SiteLinks::isLinkTo($from, $target)) {
                $log[] = "Link $from → $to: " . \Systopic\System\Config\SiteLinks::move($from, $to, $target);
            } elseif (file_exists($from)) {
                $log[] = "$from ist kein Link, sondern ein Ordner — bleibt";
            }
        }

        $site->name = $newName;
        $scope->save($site);
        $log[] = "Name $oldName → $newName";
        return array_merge($log, InstanceFiles::renameSite(self::configDir($projectRoot), $oldName, $newName));
    }

    public static function saveMeta(Scope $scope, Site $site, string $json): void
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Meta ist kein gültiges JSON-Objekt: ' . json_last_error_msg());
        }
        $site->meta = new Text(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $scope->save($site);
    }

    /**
     * The alias domains of a site — one per line or separated by commas,
     * blank lines dropped, `www.` not needed (the .htaccess strips it).
     *
     * Each is a domain with TLD, not the site's own name and not a name or
     * alias of another site: a host must lead to exactly one site.
     *
     * @return list<string> what was saved
     */
    public static function saveAliases(Scope $scope, Site $site, string $text): array
    {
        $aliases = [];
        foreach (preg_split('~[\s,]+~', strtolower($text)) ?: [] as $domain) {
            $domain = preg_replace('~^www\.~', '', trim($domain)) ?? '';
            if ($domain === '' || in_array($domain, $aliases, true)) {
                continue;
            }
            if (!self::validName($domain)) {
                throw new RuntimeException("'$domain' ist kein Domainname mit TLD.");
            }
            if ($domain === $site->name) {
                throw new RuntimeException("'$domain' ist der Name der Site selbst.");
            }
            foreach (self::all($scope) as $other) {
                if ($other !== $site && ($other->name === $domain || in_array($domain, $other->aliasList, true))) {
                    throw new RuntimeException("'$domain' gehört schon zur Site {$other->name}.");
                }
            }
            $aliases[] = $domain;
        }
        $site->aliases = new Text((string) json_encode($aliases, JSON_UNESCAPED_SLASHES));
        $scope->save($site);
        return $aliases;
    }

    /**
     * A request on an alias domain: 301 to the same address on the site's
     * canonical entry (its name on LIVE). Nothing to do on any other entry.
     */
    public static function redirectAlias(Config $config): void
    {
        $site = $config->site();
        if ($site === null || !in_array($config->siteEntry(), $site->aliases, true)) {
            return;
        }
        $canonical = $config->canonicalUrl();
        if ($canonical === null) {
            return;
        }
        $root = (string) \http::$root;
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? $root);
        $rest = str_starts_with($uri, $root) ? substr($uri, strlen($root)) : ltrim($uri, '/');
        $target = $canonical . $rest;
        \logroute('SiteRegistry: alias domain, 301 to the site', ['host' => \http::$host, 'site' => $site->name, 'target' => $target]);
        if (PHP_SAPI === 'cli') {
            echo "Location: $target\n";   // the CLI harness cannot see headers
        }
        header('Location: ' . $target, true, 301);
        exit;
    }

    /**
     * sites/<name>/ with templates/ and its subfolders, a readme.txt in each.
     * Existing folders and readmes are left alone.
     *
     * @return list<string> the folders that were created
     */
    public static function createFolders(string $name, ?string $projectRoot = null): array
    {
        $root = rtrim(str_replace('\\', '/', $projectRoot ?? (string) \fs::$projectRoot), '/');
        $site = "$root/sites/$name";
        $folders = [
            $site               => "Site $name — alles hier gilt nur für diese Site und ersetzt Gleichnamiges aus dem Projekt-Root.\nDie Site selbst (Node, Meta) wird im CMS im Seitenbaum gepflegt: Site-Knoten anklicken, die Default-Site über den Globus.\n",
            "$site/templates"   => "Templates nur für $name. Gleiche Struktur wie templates/ im Projekt-Root.\n",
        ];
        foreach (self::TEMPLATE_FOLDERS as $folder => $text) {
            $folders["$site/templates/$folder"] = $text . "\n";
        }

        $created = [];
        foreach ($folders as $dir => $readme) {
            $native = \fs::toNative($dir);
            if (!is_dir($native)) {
                if (!@mkdir($native, 0775, true) && !is_dir($native)) {
                    throw new RuntimeException("Ordner '$dir' konnte nicht angelegt werden.");
                }
                $created[] = $dir;
            }
            $file = $native . DIRECTORY_SEPARATOR . 'readme.txt';
            if (!is_file($file)) {
                file_put_contents($file, $readme);
            }
        }
        return $created;
    }

    // ----------------------------------------------------------------- import

    /**
     * One-off: the `sites` block of config.json into the table.
     *
     * The default site gets a row without a node. Every other site gets a
     * node of type 'site'; when config.json named a page as its `root`, that
     * page is moved below the new site node. Only while the table is empty.
     *
     * @return list<string> what was done, for the panel
     */
    public static function importFromConfig(Scope $scope, Config $config): array
    {
        if (self::all($scope) !== []) {
            throw new RuntimeException('Die Tabelle cms_sites ist nicht leer — Import nur einmalig.');
        }
        $sites = $config->data['sites'] ?? [];
        if (!is_array($sites) || $sites === []) {
            throw new RuntimeException('config.json enthält keine Sites.');
        }
        // the default site first, so create() makes it the node-less one
        uksort($sites, static fn($a, $b) => (int) empty($sites[$a]['default']) <=> (int) empty($sites[$b]['default']));

        $log = [];
        foreach ($sites as $name => $data) {
            $site = self::create($scope, (string) $name);
            if (is_array($data['meta'] ?? null)) {
                self::saveMeta($scope, $site, (string) json_encode($data['meta']));
            }
            $nodeId = $site->relationId('node');
            $log[] = $nodeId === null ? "$name: Default-Site" : "$name: Site-Node $nodeId";

            // the branch root config.json named: a page of the topmost level,
            // moved to the first place under its new site node
            $page = (int) ($data['root'] ?? 0);
            if ($nodeId !== null && $page > 0) {
                $pageNode = null;
                foreach (NodeChildren::of($scope, null) as $candidate) {
                    if ((int) $candidate->id === $page) {
                        $pageNode = $candidate;
                        break;
                    }
                }
                if ($pageNode === null) {
                    $log[] = "  Seite $page nicht auf der obersten Ebene gefunden — nicht verschoben";
                    continue;
                }
                $uow = $scope->uow();
                foreach (NodeOperator::placed($scope, $pageNode, $site->node, 0) as $changed) {
                    $uow->persist($changed);
                }
                $uow->commit();
                $log[] = "  Seite $page unter die Site verschoben";
            }
        }
        return $log;
    }
}
