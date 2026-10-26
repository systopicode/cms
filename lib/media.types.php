<?php
if (function_exists('mediaTypeFieldsHtml')) {
	return;
}

/**
 * media_type ist unabhängig vom Datei-Mimetype.
 * Erlaubte Uploads: mediaInfo::$mimetypes.
 * Externe Anbieter: EXTERNAL_MEDIAS in settings.php (YouTube, Vimeo, …).
 */

function mediaExternalTypes(): array {
	$raw = defined('EXTERNAL_MEDIAS') ? EXTERNAL_MEDIAS : [];
	if (!is_array($raw)) {
		return [];
	}
	$out = [];
	foreach ($raw as $label) {
		$label = trim((string) $label);
		if ($label === '') {
			continue;
		}
		$out[strtolower($label)] = $label;
	}
	return $out;
}

function mediaIsExternalType(string $type): bool {
	$type = strtolower(trim($type));
	if ($type === 'external') {
		return true;
	}
	$externals = mediaExternalTypes();
	return $type !== '' && isset($externals[$type]);
}

function mediaTypeLabels(): array {
	return [
		'image' => 'Image',
		'video' => 'Video',
		'pdf' => 'PDF',
		'file' => 'File',
	] + mediaExternalTypes();
}

function mediaTypeLabel(string $type): string {
	$labels = mediaTypeLabels();
	$type = mediaNormalizeType($type);
	if ($type === 'external') {
		return 'External';
	}
	return $labels[$type] ?? ucfirst($type);
}

function mediaNormalizeType(string $type): string {
	$type = strtolower(trim($type));
	if ($type === 'application') {
		return 'file';
	}
	if ($type === 'external' || mediaIsExternalType($type)) {
		return $type;
	}
	$known = array_keys(mediaTypeLabels());
	return in_array($type, $known, true) ? $type : '';
}

function mediaTypeFromMime(string $mime): string {
	$mime = strtolower(trim($mime));
	if (str_starts_with($mime, 'image/')) {
		return 'image';
	}
	if (str_starts_with($mime, 'video/')) {
		return 'video';
	}
	if (str_starts_with($mime, 'audio/')) {
		return 'file';
	}
	if ($mime === 'application/pdf') {
		return 'pdf';
	}
	return $mime !== '' ? 'file' : '';
}

function mediaAllowedTypesFromMimes(): array {
	$types = [];
	if (class_exists('mediaInfo') && is_array(mediaInfo::$mimetypes)) {
		foreach (array_keys(mediaInfo::$mimetypes) as $mime) {
			$key = mediaTypeFromMime((string) $mime);
			if ($key === '') {
				continue;
			}
			$types[$key] = mediaTypeLabel($key);
		}
	}
	if (!$types) {
		$types = [
			'image' => mediaTypeLabel('image'),
			'video' => mediaTypeLabel('video'),
			'pdf' => mediaTypeLabel('pdf'),
			'file' => mediaTypeLabel('file'),
		];
	}
	$ordered = [];
	foreach (['image', 'video', 'pdf', 'file'] as $key) {
		if (isset($types[$key])) {
			$ordered[$key] = $types[$key];
		}
	}
	foreach ($types as $key => $label) {
		if (!isset($ordered[$key])) {
			$ordered[$key] = $label;
		}
	}
	return $ordered;
}

function mediaAllowedTypesForGroup(string $group = ''): array {
	return mediaAllowedTypesFromMimes() + mediaExternalTypes();
}

function mediaAllowedAcceptAttr(): string {
	if (!class_exists('mediaInfo') || !is_array(mediaInfo::$mimetypes)) {
		return '';
	}
	$tokens = [];
	foreach (mediaInfo::$mimetypes as $mime => $ext) {
		foreach (explode('|', (string) $mime) as $part) {
			$part = trim($part);
			if ($part !== '') {
				$tokens[] = $part;
			}
		}
		$ext = strtolower(trim((string) $ext, ". \t\n\r"));
		if ($ext !== '') {
			$tokens[] = '.' . $ext;
		}
	}
	return implode(',', array_unique($tokens));
}

function mediaNormalizeUrl(string $url): string {
	$url = trim($url);
	if ($url === '') {
		return '';
	}
	if (!preg_match('~^https?://~i', $url)) {
		$url = 'https://' . ltrim($url, '/');
	}
	return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function mediaEffectiveType($media): string {
	if (!$media) {
		return '';
	}
	return mediaNormalizeType((string) ($media->type ?? $media->media_type ?? ''));
}

function mediaIsExternal($media): bool {
	return mediaIsExternalType(mediaEffectiveType($media));
}

/**
 * Typ-Dropdown + URL-Feld (nur bei YouTube/Vimeo/…).
 *
 * @param array{group?:string,type?:string,url?:string,autosave?:bool} $opts
 */
function mediaTypeFieldsHtml(array $opts = []): string {
	$group = (string) ($opts['group'] ?? '');
	$current = mediaNormalizeType((string) ($opts['type'] ?? ''));
	$url = (string) ($opts['url'] ?? '');
	$autosave = !empty($opts['autosave']);
	$types = mediaAllowedTypesForGroup($group);
	$externals = mediaExternalTypes();
	if ($current !== '' && !isset($types[$current])) {
		$types = [$current => mediaTypeLabel($current)] + $types;
	}
	if ($current === '' && $types) {
		$current = (string) array_key_first($types);
	}

	$typeAttrs = $autosave ? ' data-on_change="updateMediaType"' : ' data-doc="type"';
	$urlAttrs = $autosave ? ' data-on_input="updateMediaUrl"' : ' data-doc="url"';
	$urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

	$html = '<div class="inputGroup">';
	$html .= '<select name="type"' . $typeAttrs . '>';
	foreach ($types as $value => $label) {
		$sel = $value === $current ? ' selected' : '';
		$ext = isset($externals[$value]) || $value === 'external' ? ' data-external="1"' : '';
		$html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $sel . $ext . '>'
			. htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
	}
	$html .= '</select>';
	$html .= '<span class="bar"></span>';
	$html .= '<label>File Type</label>';
	$html .= '</div>';
	$html .= '<br clear="all">';
	$html .= '<div class="inputGroup mediaUrlField">';
	$html .= '<input spellcheck="off" type="url" name="url" placeholder=" " value="' . $urlEsc . '"' . $urlAttrs . '>';
	$html .= '<span class="bar"></span>';
	$html .= '<label>Media URL</label>';
	$html .= '</div>';
	$html .= '<br clear="all">';
	return $html;
}
