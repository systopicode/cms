<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Migration;

use Systopic\Cms\Refs\HtmlMigration;
use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;

/**
 * tools/migration — one-off conversions of content written in an older
 * form. Each one shows what it would do (a dry run on every render) and
 * does it on request; the rendering itself knows only the current form.
 *
 *   convert html links and images  →  Cms\Refs\HtmlMigration
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	/** what "convert html links and images" would do now */
	public array $htmlReport {
		get => $this->htmlMigration->run(FALSE);
	}

	public HtmlMigration $htmlMigration {
		get => new HtmlMigration($this->scope, $this->ownHosts);
	}

	/**
	 * The host names this site answers or answered to: its sites and their
	 * aliases, the host of this request, and the project folder - by
	 * convention the domain (projects/smp.de). Without www.
	 *
	 * @var list<string>
	 */
	public array $ownHosts {
		get {
			$hosts = [(string) \http::$host, basename(rtrim((string) \fs::$projectRoot, '/'))];
			foreach (\Systopic\System\Config\Config::current()?->sites->all() ?? [] as $site) {
				$hosts = [...$hosts, $site->name, ...$site->aliases];
			}
			$hosts = array_map(static fn(string $host): string => strtolower((string) preg_replace('~^www\.~i', '', $host)), $hosts);
			return array_values(array_unique(array_filter($hosts, static fn(string $host): bool => str_contains($host, '.'))));
		}
	}
}
