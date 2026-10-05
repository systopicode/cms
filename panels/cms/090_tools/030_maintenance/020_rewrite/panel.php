<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Rewrite;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Queries\RewritepathIndex\RewritepathIndex;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Cms\Tables\Rewritepaths\Model as Rewritepath;

/**
 * tools/maintenance/rewrite — every rewrite path, to rebuild or clear them.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	/** @var list<array{path: Rewritepath, page: ?\Systopic\Cms\Tables\Pages\Model, node: ?\Systopic\System\Tables\Nodes\Model}> */
	public array $paths {
		get => RewritepathIndex::list($this->scope);
	}

	public ?Rewritepath $rewritepath {
		get => RewritepathIndex::byId($this->scope, (int) \http::dataset('rewritepath_id'));
	}

	/**
	 * Rebuild the automatic path of every page that is not in the trash,
	 * one page after the other: each rebuild asks the database which paths
	 * are taken, so each is saved before the next.
	 *
	 * A page that cannot get its path is reported and skipped; the others go on.
	 *
	 * @return array{0: int, 1: list<string>} how many path rows were written, and the failures
	 */
	public function rebuildAllPaths(): array {
		[$written, $failures] = [0, []];
		foreach (RewritepathIndex::pages($this->scope) as ['nodeId' => $nodeId, 'lang' => $lang]) {
			$page = Page::load($this->scope, new Params(lang: $lang, preview: TRUE, inCms: TRUE, nodeType: 'page'), $nodeId);
			if ($page?->row === NULL) {
				continue;
			}
			try {
				$rows = $page->rewritepaths->rebuildAuto();
				$this->scope->save(...$rows);
				$written += count($rows);
			} catch (\PDOException | \RuntimeException $e) {
				$failures[] = "node $nodeId ($lang): " . $e->getMessage();
			}
		}
		return [$written, $failures];
	}
}
