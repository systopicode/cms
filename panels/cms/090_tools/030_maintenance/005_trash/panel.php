<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Trash;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\Branch\Branch;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Queries\TrashedPages\TrashedPages;
use Systopic\Cms\Tables\Pages\Languages;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;

/**
 * tools/maintenance/trash — the trashed branches, to get them back.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	/** @var array<int, array{node: \Systopic\System\Tables\Nodes\Model, pages: list<\Systopic\Cms\Tables\Pages\Model>}> */
	public array $trashed {
		get => TrashedPages::of($this->scope);
	}

	/** The language a page is written in unless it says otherwise. */
	public function siteLanguage(): string
	{
		if (defined('DEFAULT_LANG') && DEFAULT_LANG !== '') {
			return (string) DEFAULT_LANG;
		}
		return Languages::default();
	}

	/** @param list<\Systopic\Cms\Tables\Pages\Model> $pages */
	public function pageTitle(array $pages): string
	{
		$fallback = '';
		foreach ($pages as $page) {
			$label = trim((string) $page->label);
			if ($label === '') {
				continue;
			}
			if ($page->languageIso === $this->siteLanguage()) {
				return $label;
			}
			$fallback = $fallback !== '' ? $fallback : $label;
		}
		return $fallback !== '' ? $fallback : 'Untitled';
	}

	/**
	 * A branch out of the trash, everything below it too.
	 *
	 * @return int how many nodes came back
	 */
	public function restoreBranch(int $nodeId): int {
		// The branch's pages are not needed, only its nodes: any language will do.
		$changed = NodeOperator::trash(Branch::of($this->scope, new Params(lang: ''), $nodeId)->nodes, FALSE);
		$this->scope->save(...$changed);
		return count($changed);
	}
}
