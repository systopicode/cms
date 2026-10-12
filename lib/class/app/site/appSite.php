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
	private function buildJsonLd(): string {
		$tempPage = self::selectedPage();
		$scriptTag = '';
		if (isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->meta->jsonLd)))) {
			$scriptTag = '<script type="application/ld+json">';
			$scriptTag .= $tempPage->meta->jsonLd;
			$scriptTag .= '</script>';
		}
		return $scriptTag;
	}

	private function buildTitle(): string {
		$sep = self::siteMeta('titleSeparator', 'PAGE_TITLE_SEPERATOR', '|');
		$tempPage = self::selectedPage();
		if (isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->meta->shareTitle)))) {
			$pageLabel = $tempPage->meta->shareTitle . ' ' . $sep . ' ';
		} elseif (isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->label->value)))) {
			$pageLabel = $tempPage->label->value . ' ' . $sep . ' ';
		} else {
			$pageLabel = '';
		}
		$pageLabel = strtolower($pageLabel) == 'home ' . $sep . ' ' ? '' : $pageLabel;
		$label = $tempPage ? strtolower((string) $tempPage->label->value) : '';
		$PUBLIC_PAGE_NAME = self::siteMeta('title', 'PUBLIC_PAGE_NAME', 'fluxo cms');
		$PUBLIC_TITLE = $pageLabel;
		if ($label != 'home' && $label != 'start' && trim(str_replace($sep, '', $pageLabel)) != trim($PUBLIC_PAGE_NAME)) {
			$PUBLIC_TITLE .= $PUBLIC_PAGE_NAME;
		} else {
			$PUBLIC_TITLE = $PUBLIC_PAGE_NAME;
		}

		switch (STAGE) {
			case 'DEV':
				$PUBLIC_TITLE = "DEV | $PUBLIC_TITLE";
				break;
			case 'RELEASE':
				$PUBLIC_TITLE = "RELEASE | $PUBLIC_TITLE";
				break;
		}
		return trim(strip_tags((string) $PUBLIC_TITLE));
	}

	private function buildDescription(): string {
		$description = '';
		$tempPage = self::selectedPage();
		if (isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->meta->description)))) {
			$description = $tempPage->meta->description;
		} else {
			$description = self::siteMeta('description', 'META_DESCRIPTION');
		}
		return $description;
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
		$robots = self::siteMeta('robots', 'META_ROBOTS', 'index, follow');
		$tempPage = self::selectedPage();
		if (isset($tempPage) && !empty(trim(strip_tags((string) $tempPage->meta->robots)))) {
			$robots = $tempPage->meta->robots;
		}
		return $robots;
	}

	private function buildLanguage(): string {
		$language = 'de-de';
		$tempPage = self::selectedPage();
		if (isset($tempPage) && $tempPage->language !== '' && $tempPage->language !== '*') {
			$language = $tempPage->language . '-' . $tempPage->language;
		} elseif (defined('DEFAULT_LANG')) {
			$language = DEFAULT_LANG . '-' . DEFAULT_LANG;
		}
		return $language;
	}

	private function buildUrl(): string {
		$tempPage = self::selectedPage();
		return $tempPage ? (string) $tempPage->href : "";
	}

	private function buildShareImage(): string {
		$html = '';
		$tempPage = self::selectedPage();
		$shareImageId = $tempPage?->meta->shareImageId;
		$shareMedia = $shareImageId ? \Systopic\System\Tables\Medias\Media::byNodeId(\Systopic\Db\Scope::default(), $shareImageId) : null;
		if ($shareMedia !== null) {

			$shareImage = $shareMedia->setFormat('shareImage')->src;
			if (\Systopic\System\Panels\Tree\PanelTree::getInstance()?->inCms() ?? false) {
				$html = "<a class='ajax' href='editor/file/?target_id={$shareImageId}&format=shareImage'>";
				$html .= "<img title='Crop Image' src='$shareImage' />";
				$html .= '</a>';
			} else {
				return $shareImage;
			}
		} else {
			if (\Systopic\System\Panels\Tree\PanelTree::getInstance()?->inCms() ?? false) {
				$html = '';
			} else {
				// the site's default share image: absolute url, or a path below the site root
				$configured = self::siteMeta('shareImage', 'META_SHARE_IMAGE', '');
				if ($configured === '') {
					return http::$siteUrl . 'img/share_default.jpg';
				}
				return preg_match('~^https?://~', $configured) ? $configured : http::$siteUrl . ltrim($configured, '/');
			}
		}

		return $html;
	}
}
