<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Duplicates;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\Duplicates\Duplicates;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\Cms\Tables\Pages\Model as PageRow;

/**
 * tools/maintenance/duplicates — pages and crops that exist twice, to delete
 * the copies by hand.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	/** @var list<PageRow> */
	public array $pages {
		get => Duplicates::pages($this->scope);
	}

	/** @var list<Crop> */
	public array $crops {
		get => Duplicates::crops($this->scope);
	}

	public ?PageRow $page {
		get => Duplicates::page($this->scope, (int) \http::dataset('page_id'));
	}

	public ?Crop $crop {
		get => Duplicates::crop($this->scope, (int) \http::dataset('crop_id'));
	}

	/** Remove a row; a row other rows still point at says so instead. */
	public function drop(?\Systopic\Db\TableRow $row): void {
		if ($row === NULL) {
			return;
		}
		try {
			$this->scope->remove($row);
		} catch (\PDOException $e) {
			\message::error('Could not delete: ' . $e->getMessage());
		}
	}
}
