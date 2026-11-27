<?php

/**
 * Seiten-Meta für Head/Sidebar; Werte werden aus der aktuellen Route/Page abgeleitet.
 */
class appSite {

	public string $jsonLd {
		get => $this->buildJsonLd();
	}

	public string $title {
		get => $this->buildTitle();
	}

	public string $description {
		get => $this->buildDescription();
	}

	public string $keywords {
		get => $this->buildKeywords();
	}

	public string $robots {
		get => $this->buildRobots();
	}

	public string $language {
		get => $this->buildLanguage();
	}

	public string $url {
		get => $this->buildUrl();
	}

	public string $shareImage {
		get => $this->buildShareImage();
	}

	public string $author {
		get => self::siteMeta('author', 'META_AUTHOR', 'systopic GmbH');
	}

	public string $appName {
		get => self::siteMeta('appName', 'PUBLIC_APP_NAME', defined('META_AUTHOR') ? META_AUTHOR : '');
	}

	/** The website's name, not the title of the page that is open. */
	public string $siteName {
		get => self::siteMeta('title', 'PUBLIC_PAGE_NAME', '');
	}

	/** One escaped metadata block for the first HTML, navigation and the editor's check. */
	public function headTags(?\Systopic\Cms\Tables\Pages\Page $page = null): string {
		$page ??= self::selectedPage();
		if ($page === null) return '';
		$record = $this->record($page);
		$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$tags = ['<title data-cms-meta="1">' . $e($this->buildTitle($page)) . '</title>'];
		$meta = function(string $key, string $value, bool $property = false) use (&$tags, $e): void {
			if ($value !== '') $tags[] = '<meta data-cms-meta="1" ' . ($property ? 'property' : 'name') . '="' . $e($key) . '" content="' . $e($value) . '">';
		};
		$meta('description', $record['description']);
		$meta('robots', $this->robotsFor($page));
		$meta('language', $record['language']);
		if ($record['url'] !== '') $tags[] = '<link data-cms-meta="1" rel="canonical" href="' . $e($record['url']) . '">';
		foreach ($this->alternates($page) as $language => $url) $tags[] = '<link data-cms-meta="1" rel="alternate" hreflang="' . $e($language) . '" href="' . $e($url) . '">';
		$meta('og:type', 'website', true);
		$meta('og:title', $this->buildTitle($page), true);
		$meta('og:description', $record['description'], true);
		$meta('og:url', $record['url'], true);
		$meta('og:site_name', $this->siteName, true);
		$meta('og:locale', str_replace('-', '_', $record['language']), true);
		$meta('og:image', $record['image'], true);
		$meta('twitter:card', 'summary_large_image');
		$tags[] = $this->buildJsonLd($page);
		return implode("\n", $tags) . "\n";
	}

	/** Compatibility for a project that still asks for the old first-response helper. */
	public function crawlerTags(): string { return $this->headTags(); }

	/** Real public translations, with an additional fallback in the configured default language. */
	public function alternates(\Systopic\Cms\Tables\Pages\Page $page): array {
		if (!\Systopic\Cms\Search\PublicPages::siteIsPublic()) return [];
		$page = \Systopic\Cms\Search\PublicPages::canonical($page);
		$links = [];
		foreach (\Systopic\Cms\Tables\Pages\Languages::activated() as $code => $definition) {
			$translated = \Systopic\Cms\Tables\Pages\Page::byId(\Systopic\Db\Scope::default(), \Systopic\Cms\Search\PublicPages::params($code), $page->id);
			if ($translated?->row === null || !$translated->meta->published || str_contains(strtolower((string) $translated->meta->robots), 'noindex')) continue;
			$url = (string) $translated->href;
			$links[\Systopic\Cms\Tables\Pages\Languages::tag($code)] = $url;
			if ($code === \Systopic\Cms\Tables\Pages\Languages::default()) $links['x-default'] = $url;
		}
		return $links;
	}

	public function robotsFor(\Systopic\Cms\Tables\Pages\Page $page): string {
		$robots = trim((string) $page->meta->robots) ?: self::siteMeta('robots', 'META_ROBOTS', 'index, follow');
		if (\Systopic\Cms\Search\PublicPages::noIndexEnvironment() || !\Systopic\Cms\Search\PublicPages::siteIsPublic()
			|| !$page->meta->published || $page->templateName === '' || app::request()->preview || app::request()->showeditableareas) return 'noindex, nofollow';
		return $robots;
	}

	public string $topic {
		get => self::siteMeta('topic', 'META_PAGE_TOPIC', '');
	}

	/**
	 * A site-wide default. Multi-site: the served site's `meta` block in
	 * config/config.json comes first (every site has its own title, author,
	 * description), the constant from settings.php second, the built-in
	 * default last.
	 */
	private static function siteMeta(string $key, string $constant, string $default = ''): string {
		$value = \Systopic\System\Config\Config::current()?->site()?->meta($key);
		if (is_string($value) && $value !== '') {
			return $value;
		}
		return defined($constant) ? (string) constant($constant) : $default;
	}

	/**
	 * The page all meta tags describe: the one the URL addressed.
	 *
	 * Was route::$selectedNode->page. PageAccess::$selectedPage is filled by
	 * PageAccess::check() from Route, and is null on paths that never
	 * resolved a page (panel requests, CMS preview before build) — every
	 * caller below already guards with isset(), the two that did not now do.
	 */
	private static function selectedPage(): ?\Systopic\Cms\Tables\Pages\Page {
		return \Systopic\Cms\Pages\Route\PageAccess::$selectedPage
			?? \Systopic\Cms\Pages\Route\Route::getInstance()?->getSelected()?->page;
	}
	/**
	 * One WebPage node from the page itself: title, description, language,
	 * share image, dates. A stored meta_json_ld is added only when it is
	 * valid JSON, so a broken paste never reaches the page.
	 */
	/**
	 * What this page contributes to search engines and AI, from its current
	 * fields. The public page and the SEO panel both read this, so a change
	 * shows up in both on the next request.
	 *
	 * @return array{name: string, description: string, descriptionFrom: string, language: string, url: string, image: string, imageFrom: string, crumbs: list<string>, listed: bool, blockers: list<string>}
	 */
	public function record(\Systopic\Cms\Tables\Pages\Page $page): array {
		$name = trim(strip_tags((string) ($page->meta->shareTitle ?: $page->label->value)));
		$own = trim(strip_tags((string) $page->meta->description));
		if ($own !== '') {
			$description = $own;
			$descriptionFrom = 'Description field';
		} else {
			$excerpt = $this->pageExcerpt($page);
			if ($excerpt !== '') {
				$description = $excerpt;
				$descriptionFrom = 'First sentence on the page';
			} else {
				$description = self::siteMeta('description', 'META_DESCRIPTION');
				$descriptionFrom = 'Site default';
			}
		}
		$image = $this->imageOf($page);
		$crumbs = [];
		$trail = $this->breadcrumb($page);
		if ($trail !== null) {
			foreach ($trail['itemListElement'] as $item) {
				$crumbs[] = (string) $item['name'];
			}
		}
		return [
			'name' => $name,
			'description' => trim(strip_tags($description)),
			'descriptionFrom' => $descriptionFrom,
			'language' => $this->languageOf($page),
			'url' => $this->absoluteUrl((string) \Systopic\Cms\Search\PublicPages::canonical($page)->href),
			'image' => $image['url'],
			'imageFrom' => $image['from'],
			'crumbs' => $crumbs,
			'listed' => $page->meta->listed,
			'blockers' => $page->meta->listBlockers,
		];
	}

	private function buildJsonLd(?\Systopic\Cms\Tables\Pages\Page $page = null): string {
		$page ??= self::selectedPage();
		if ($page === null) {
			return '';
		}
		$record = $this->record($page);
		if ($record['name'] === '') {
			return '';
		}
		$node = [
			'@type' => 'WebPage',
			'name' => $record['name'],
			'inLanguage' => $record['language'],
		];
		if ($record['url'] !== '') {
			$node['@id'] = $record['url'] . '#webpage';
			$node['url'] = $record['url'];
		}
		if ($record['description'] !== '') {
			$node['description'] = $record['description'];
		}
		if ($record['image'] !== '') {
			$node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $record['image']];
		}
		$siteName = self::siteMeta('title', 'PUBLIC_PAGE_NAME', '');
		$siteUrl = rtrim((string) http::$siteUrl, '/') . '/';
		if ($siteName !== '' || $siteUrl !== '/') {
			$node['isPartOf'] = array_filter([
				'@type' => 'WebSite',
				'name' => $siteName !== '' ? $siteName : null,
				'url' => $siteUrl !== '/' ? $siteUrl : null,
			]);
		}
		$row = $page->row;
		if ($row?->modifiedDate instanceof \DateTimeImmutable) {
			$node['dateModified'] = $row->modifiedDate->format(\DateTimeInterface::ATOM);
		}
		if ($row?->createdDate instanceof \DateTimeImmutable) {
			$node['datePublished'] = $row->createdDate->format(\DateTimeInterface::ATOM);
		}
		$graph = [$node];
		$crumbs = $this->breadcrumb($page);
		if ($crumbs !== null) {
			$graph[] = $crumbs;
		}
		foreach ($this->extraJsonLd($page->meta->jsonLd) as $extra) {
			if (($extra['@type'] ?? '') === 'WebPage') {
				// Preserve additional relations, while the current page owns its identity and facts.
				$graph[0] = array_replace($extra, $graph[0]);
			} elseif (($extra['@type'] ?? '') !== 'BreadcrumbList') $graph[] = $extra;
		}
		$json = json_encode(
			['@context' => 'https://schema.org', '@graph' => $graph],
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
		);
		return $json === false ? '' : '<script data-cms-meta="1" type="application/ld+json">' . $json . '</script>';
	}

	/**
	 * Where the page sits in the tree. One step is not a trail, so a page
	 * at the top of the site gets none.
	 *
	 * @return array<string, mixed>|null
	 */
	private function breadcrumb(\Systopic\Cms\Tables\Pages\Page $page): ?array {
		$items = [];
		$add = function (string $name, string $href) use (&$items): void {
			$name = trim(strip_tags($name));
			if ($name === '') {
				return;
			}
			$item = [
				'@type' => 'ListItem',
				'position' => count($items) + 1,
				'name' => $name,
			];
			$url = $this->absoluteUrl($href);
			if ($url !== '') {
				$item['item'] = $url;
			}
			$items[] = $item;
		};
		foreach ($page->ancestors as $ancestor) {
			$add((string) $ancestor->label->value, (string) $ancestor->href);
		}
		$add((string) ($page->meta->shareTitle ?: $page->label->value), (string) $page->href);
		if (count($items) < 2) {
			return null;
		}
		return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
	}

	/** First real sentence on the page, when the meta description is empty. */
	private function pageExcerpt(\Systopic\Cms\Tables\Pages\Page $page): string {
		foreach (['html01', 'html02', 'html03', 'text02', 'text03', 'text04', 'text01'] as $name) {
			$text = html_entity_decode(strip_tags((string) $page->$name->value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? '');
			if (mb_strlen($text) >= 40) {
				return mb_substr($text, 0, 160);
			}
		}
		return '';
	}

	/** A public URL. Relative paths are resolved against the site root. */
	private function absoluteUrl(string $path): string {
		$path = trim($path);
		if ($path === '') {
			return '';
		}
		if (preg_match('~^https?://~i', $path)) {
			return $path;
		}
		// An address from the root (`/projects/x/public/var/…` - Media::$src): the site's url already ends in it.
		$root = (string) http::$root;
		if ($root !== '/' && $root !== '' && str_starts_with($path, $root)) {
			$path = substr($path, strlen($root));
		}
		return rtrim((string) http::$siteUrl, '/') . '/' . ltrim($path, '/');
	}

	/** The share image as a URL, never the crop control the CMS draws. */
	private function shareImageUrl(): string {
		$page = self::selectedPage();
		$id = $page?->meta->shareImageId;
		if ($id) {
			$media = \Systopic\System\Tables\Medias\Media::byNodeId(\Systopic\Db\Scope::default(), $id);
			if ($media !== null) {
				return $this->absoluteUrl((string) $media->setFormat('shareImage')->src);
			}
		}
		$configured = self::siteMeta('shareImage', 'META_SHARE_IMAGE', '');
		if ($configured === '') {
			return \Systopic\System\Settings\ThemeAssets::projectHref('share') ?? $this->absoluteUrl('img/share_default.jpg');
		}
		return $this->absoluteUrl($configured);
	}

	/**
	 * Extra nodes somebody stored earlier. Invalid JSON is dropped.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function extraJsonLd(?string $raw): array {
		$raw = trim(strip_tags((string) $raw));
		if ($raw === '') {
			return [];
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}
		if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
			$decoded = $decoded['@graph'];
		} elseif (isset($decoded['@type'])) {
			unset($decoded['@context']);
			$decoded = [$decoded];
		}
		$nodes = [];
		foreach ($decoded as $node) {
			if (is_array($node) && isset($node['@type'])) {
				$nodes[] = $node;
			}
		}
		return $nodes;
	}

	private function buildTitle(?\Systopic\Cms\Tables\Pages\Page $tempPage = null): string {
		$sep = self::siteMeta('titleSeparator', 'PAGE_TITLE_SEPERATOR', '|');
		$tempPage ??= self::selectedPage();
		if (isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->meta->shareTitle)))) {
			$pageLabel = $tempPage->meta->shareTitle . ' ' . $sep . ' ';
		} elseif (isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->label->value)))) {
			$pageLabel = $tempPage->label->value . ' ' . $sep . ' ';
		} else {
			$pageLabel = '';
		}
		$pageLabel = strtolower($pageLabel) == 'home ' . $sep . ' ' ? '' : $pageLabel;
		$label = $tempPage ? strtolower((string) $tempPage->label->value) : '';
		$PUBLIC_PAGE_NAME = self::siteMeta('title', 'PUBLIC_PAGE_NAME', 'fluxo');
		$PUBLIC_TITLE = $pageLabel;
		if ($label != 'home' && $label != 'start' && trim(str_replace($sep, '', $pageLabel)) != trim($PUBLIC_PAGE_NAME)) {
			$PUBLIC_TITLE .= $PUBLIC_PAGE_NAME;
		} else {
			$PUBLIC_TITLE = $PUBLIC_PAGE_NAME;
		}

		return \Systopic\System\Sys\WebApp::stageMark() . trim(strip_tags((string) $PUBLIC_TITLE));
	}

	private function buildDescription(): string {
		$page = self::selectedPage();
		return $page === null
			? self::siteMeta('description', 'META_DESCRIPTION')
			: $this->record($page)['description'];
	}

	/** BCP 47 tag for a page, from its language. */
	private function languageOf(\Systopic\Cms\Tables\Pages\Page $page): string {
		return \Systopic\Cms\Tables\Pages\Languages::tag($page->language !== '*' && $page->language !== ''
			? $page->language : \Systopic\Cms\Tables\Pages\Languages::default());
	}

	/** @return array{url: string, from: string} */
	private function imageOf(\Systopic\Cms\Tables\Pages\Page $page): array {
		$id = $page->meta->shareImageId;
		if ($id) {
			$media = \Systopic\System\Tables\Medias\Media::byNodeId(\Systopic\Db\Scope::default(), $id);
			if ($media !== null) {
				return ['url' => $this->absoluteUrl((string) $media->setFormat('shareImage')->src), 'from' => 'Share image'];
			}
		}
		$configured = self::siteMeta('shareImage', 'META_SHARE_IMAGE', '');
		$url = $configured === ''
			? (\Systopic\System\Settings\ThemeAssets::projectHref('share') ?? $this->absoluteUrl('img/share_default.jpg'))
			: $this->absoluteUrl($configured);
		return ['url' => $url, 'from' => 'Site default'];
	}

	private function buildKeywords(): string {
		$keywords = '';
		$tempPage = self::selectedPage();
		if (FALSE && isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->meta->keywords)))) {
			$keywords = $tempPage->meta->keywords;
		} else {
			$keywords = self::siteMeta('keywords', 'META_KEYWORDS');
		}
		return $keywords;
	}

	private function buildRobots(): string {
		$page = self::selectedPage();
		return $page ? $this->robotsFor($page) : self::siteMeta('robots', 'META_ROBOTS', 'index, follow');
	}

	private function buildLanguage(): string {
		$page = self::selectedPage();
		return $page ? $this->languageOf($page) : \Systopic\Cms\Tables\Pages\Languages::tag(\Systopic\Cms\Tables\Pages\Languages::default());
	}

	private function buildUrl(): string {
		$tempPage = self::selectedPage();
		return $tempPage ? (string) \Systopic\Cms\Search\PublicPages::canonical($tempPage)->href : "";
	}

	/** The CMS draws its own (pages sidebar, Panel::shareImage()). */
	private function buildShareImage(): string {
		$shareImageId = self::selectedPage()?->meta->shareImageId;
		$shareMedia = $shareImageId ? \Systopic\System\Tables\Medias\Media::byNodeId(\Systopic\Db\Scope::default(), $shareImageId) : null;
		if ($shareMedia !== null) {
			return (string) $shareMedia->setFormat('shareImage')->src;
		}
		// the site's default share image: absolute url, or a path below the site root
		$configured = self::siteMeta('shareImage', 'META_SHARE_IMAGE', '');
		if ($configured === '') {
			return \Systopic\System\Settings\ThemeAssets::projectHref('share') ?? http::$siteUrl . 'img/share_default.jpg';
		}
		return preg_match('~^https?://~', $configured) ? $configured : http::$siteUrl . ltrim($configured, '/');
	}
}
