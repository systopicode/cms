<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Image;

use Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel as FilePanel;
use Systopic\System\Media\Ratio;
use Systopic\System\Media\Variants;

/**
 * The image editor (sys.cropEditor) in the file editor's frame. Its tabs
 * are panels below it, sharing this class (`bequests`):
 *
 *   …/editor/file/image/master/?target_id=…   turn, look and crop of the whole picture
 *   …/editor/file/image/crops/?target_id=…    one crop per aspect ratio
 *   …/editor/file/image/formats/?target_id=…  the fixed formats (Variants::formats()): exact pixels, each its own crop
 *   …/editor/file/image/files/?target_id=…    every file of the picture, made or not (Media\VariantList)
 *   …/editor/file/image/<action>?target_id=…  its requests (actions.php: Media\CropEditorEndpoint)
 *
 * At the file editor's own address (`editor/file/?target_id=…`) it shows
 * the master - or, with `&format=<…>` (the page editor asks so for a
 * picture of the page), a fixed format (`shareImage`) in its tab, a ratio
 * or an alias of one (`16:9`, `16by9`, `fullWidth`) among the crops.
 */
class Panel extends FilePanel {

	/** The tabs share this class. */
	public bool $bequests = true;

	/** The tabs: name => label, icon, what it is for. */
	public const TABS = [
		'master' => ['Edit master', 'image', 'Turn, look and crop of the whole picture'],
		'crops' => ['Edit crops', 'crop-simple', 'One crop per aspect ratio'],
		'formats' => ['Edit formats', 'crop-simple', 'Pictures of exact pixels, each its own crop - the share image'],
		'files' => ['Show files', 'files', 'Every file of this picture: made, or made on its first request'],
	];

	/** The tabs there are: the formats only where the project has fixed formats. */
	public function tabList(): array {
		return Variants::formats() === [] ? array_diff_key(self::TABS, ['formats' => 1]) : self::TABS;
	}

	/** The image editor's own panel - from a tab, too. */
	public function image(): self {
		$panel = $this;
		while ($panel->parent instanceof self) {
			$panel = $panel->parent;
		}
		return $panel;
	}

	/** The tab on screen: the address's, else what was asked for (`format`), else the master. */
	public string $tab {
		get {
			$selected = $this->image()->childSelected;
			if ($selected !== NULL && isset($this->tabList()[$selected->name])) {
				return $selected->name;
			}
			if ($this->formatAsked !== NULL) {
				return 'formats';
			}
			return $this->ratioAsked !== NULL ? 'crops' : 'master';
		}
	}

	/**
	 * The ratio `&format=` asked for: a ratio (`16:9`, `16by9`) or a format
	 * name (an alias of a ratio and a size). Null for none, the original,
	 * or a name without a ratio.
	 */
	public ?string $ratioAsked {
		get {
			$format = trim((string) ($this->file()->state->format ?? ''));
			if ($format === '' || $format === Variants::MASTER || Variants::format($format) !== NULL) {
				return NULL;
			}
			$ratio = Ratio::of(str_ireplace('by', ':', $format));
			if ($ratio === NULL && class_exists('mediaFormats', false)) {
				$named = \mediaFormats::get()[$format] ?? NULL;
				$ratio = is_array($named) ? Variants::alias($named)['ratio'] : NULL;
			}
			return $ratio === NULL ? NULL : (Variants::ratio($ratio->name) ?? Variants::nearestRatio($ratio))->name;
		}
	}

	/** The fixed format `&format=` asked for (`shareImage`) - null for any other. */
	public ?string $formatAsked {
		get {
			$format = trim((string) ($this->file()->state->format ?? ''));
			return $format !== '' && Variants::format($format) !== NULL ? $format : NULL;
		}
	}

	/** What the editor shows first in its tab: the fixed format, or the ratio, asked for. */
	public ?string $shownFirst {
		get => $this->tab === 'formats' ? $this->formatAsked : ($this->tab === 'crops' ? $this->ratioAsked : NULL);
	}

	/** The tabs in the frame's header: links, each its own address. */
	public function tabs(): string {
		$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		$here = $this->tab;
		$html = '<nav class="ceTabs" role="tablist" aria-label="What to edit">';
		foreach ($this->tabList() as $name => [$label, $icon, $title]) {
			$html .= '<a class="ajax" role="tab" href="' . $e($this->image()->href . $name . '/?target_id=' . $this->targetId) . '" title="' . $e($title) . '"'
				. ' aria-selected="' . ($name === $here ? 'true' : 'false') . '"><i class="cmsIcon cmsIcon-' . $e($icon) . '" aria-hidden="true"></i><span>' . $e($label) . '</span></a>';
		}
		return $html . '</nav>';
	}
}
