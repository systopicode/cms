<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\Misses;

use DateTimeImmutable;
use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\Cms\Tables\Misses\Model as Miss;
use Systopic\Cms\Tables\Pages\Languages;
use Systopic\Cms\Tables\Pages\Model as PageRow;

/**
 * Addresses that were requested and matched no page.
 *
 * A panel path (the CMS, files) is not a miss. The same address is one row;
 * every further request only counts.
 */
final class Misses
{
    /** First segments that belong to the CMS or to files, never to a page. */
    private const SKIP = ['cms', 'auth', 'var', 'assets', 'css', 'js', 'img', 'fonts', 'system', 'packages'];

    public static function note(Scope $scope, string $path, ?int $siteNodeId): void
    {
        $path = trim($path, '/');
        if ($path === '' || strlen($path) > 191 || preg_match('~^[\\p{L}0-9_./\\-]+$~u', $path) !== 1) {
            return;
        }
        $first = strtolower(explode('/', $path, 2)[0]);
        if (in_array($first, self::SKIP, true) || str_contains($path, '.')) {
            return;
        }
        $lock = 'cms-miss-' . substr(hash('sha256', (string) $scope->connection->database . ':' . $siteNodeId . ':' . $path), 0, 48);
        $locked = false;
        try {
            // Same connection as the ORM, so concurrent first visits cannot create duplicates.
            $locked = (int) $scope->connection->value('SELECT GET_LOCK(?, 1)', [$lock]) === 1;
            if (!$locked) {
                return;
            }
            $existing = self::one($scope, $path, $siteNodeId);
            if ($existing !== null) {
                $existing->hits++;
                $existing->lastAt = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $scope->save($existing);
                return;
            }
            $miss = new Miss();
            $miss->path = $path;
            $miss->siteNodeId = $siteNodeId;
            $miss->hits = 1;
            $miss->lastAt = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $scope->save($miss);
        } catch (\Throwable $e) {
            \logroute('Misses::note failed', $e->getMessage());
        } finally {
            if ($locked) {
                $scope->connection->value('SELECT RELEASE_LOCK(?)', [$lock]);
            }
        }
    }

    /** @return list<Miss> */
    public static function list(Scope $scope): array
    {
        Registry::register(Miss::class);
        return $scope->rows(
            __DIR__ . '/Misses.list.sql',
            [],
            provisioner: new Provisioner($scope->connection),
        )->all(Miss::class);
    }

    /** Total for the dashboard, independently of the panel's page size. */
    public static function count(Scope $scope): int
    {
        Registry::register(Miss::class);
        $rows = $scope->rows(__DIR__ . '/Misses.count.sql', [], cache: false,
            provisioner: new Provisioner($scope->connection))->looseValues();
        foreach ($rows as $row) {
            return (int) $row['total'];
        }
        return 0;
    }

    public static function one(Scope $scope, string $path, ?int $site): ?Miss
    {
        Registry::register(Miss::class);
        return $scope->rows(__DIR__ . '/Misses.one.sql', [
            'path' => $path,
            'site' => $site,
        ], cache: false, provisioner: new Provisioner($scope->connection))->first(Miss::class);
    }

    /** @return list<array{node: int, label: string}> */
    public static function pages(Scope $scope): array
    {
        $rows = [];
        foreach ($scope->rows(__DIR__ . '/Misses.pages.sql', [
            'lang' => Languages::default(),
        ])->looseValues() as $row) {
            $rows[] = ['node' => (int) $row['node'], 'label' => trim((string) $row['label'])];
        }
        return $rows;
    }

    public static function pageOf(Scope $scope, int $nodeId): ?PageRow
    {
        Registry::register(PageRow::class);
        return $scope->rows(__DIR__ . '/Misses.page.sql', [
            'node' => $nodeId,
            'lang' => Languages::default(),
        ])->first(PageRow::class);
    }

    public static function byId(Scope $scope, int $id): ?Miss
    {
        Registry::register(Miss::class);
        return $scope->rows(__DIR__ . '/Misses.byId.sql', [
            'id' => $id,
        ], provisioner: new Provisioner($scope->connection))->first(Miss::class);
    }
}
