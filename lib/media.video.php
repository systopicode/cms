<?php
if (function_exists('videoFormatsAfterUpload')) {
	return;
}
/**
 * Video-Formate wie Bildformate: var/formats/<format>/<ordner>/<datei>.mp4
 * Erzeugt per ffmpeg beim ersten Aufruf der Format-URL (bzw. HEAD-Request aus dem Frontend).
 * Veraltet, sobald das Original neuer ist. ?rebuild erzwingt neu.
 */

function videoFormatConfig(string $format): ?array {
	$formats = defined('VIDEO_FORMATS') ? VIDEO_FORMATS : [];
	return isset($formats[$format]) && is_array($formats[$format]) ? $formats[$format] : null;
}

/** @return array{folders:string,filename:string}|null */
function videoFormatMediaParts($media): ?array {
	if (!$media) {
		return null;
	}
	$filename = (string) ($media->filename ?? $media->media_filename ?? '');
	$mediaId = (int) ($media->id ?? $media->media_id ?? 0);
	$folders = (string) ($media->folders ?? '');
	if ($folders === '' && $mediaId && class_exists('media')) {
		$folders = media::id2folders($mediaId);
	}
	if ($folders === '' || !preg_match('/^[A-Za-z0-9._-]+\.(mp4|mov|m4v|webm)$/i', $filename)) {
		return null;
	}
	return ['folders' => $folders, 'filename' => $filename];
}

function videoFormatRel(string $format, string $folders, string $filename): string {
	$stem = preg_replace('/\.(mp4|mov|m4v|webm)$/i', '', $filename);
	return 'var/formats/' . $format . '/' . $folders . $stem . '.mp4';
}

function videoFormatSiteRoot(): string {
	$root = (string) (fs::$siteRoot ?: fs::$root ?: (getcwd() . '/'));
	return rtrim($root, '/') . '/';
}

/** Nur eingeloggte Nutzer stoßen ffmpeg an, Besucher bekommen bis dahin das Original. */
function videoFormatCanBuild(): bool {
	if (!class_exists('user')) {
		return false;
	}
	try {
		user::load();
		return user::known();
	} catch (Throwable $e) {
		return false;
	}
}

function videoFormatIsFresh(string $formatAbs, string $originalAbs): bool {
	return is_file($formatAbs)
		&& filesize($formatAbs) > 0
		&& is_file($originalAbs)
		&& filemtime($formatAbs) >= filemtime($originalAbs);
}

/**
 * Fertige und (für Eingeloggte) noch fehlende Fassungen, je als [Breite, URL], klein → groß.
 * @param string[] $formats
 * @return array{sources:array<int,array{0:int,1:string}>,build:array<int,array{0:int,1:string}>}
 */
function videoFormatSourceSet($media, array $formats): array {
	$out = ['sources' => [], 'build' => []];
	$parts = videoFormatMediaParts($media);
	if (!$parts) {
		return $out;
	}
	$root = videoFormatSiteRoot();
	$original = $root . 'var/original/' . $parts['folders'] . $parts['filename'];
	if (!is_file($original)) {
		return $out;
	}
	$base = rtrim((string) (http::$root ?? '/'), '/') . '/';
	$canBuild = null;
	foreach ($formats as $format) {
		$config = videoFormatConfig($format);
		if (!$config) {
			continue;
		}
		$w = (int) ($config['max_width'] ?? 0);
		$rel = videoFormatRel($format, $parts['folders'], $parts['filename']);
		if (videoFormatIsFresh($root . $rel, $original)) {
			$out['sources'][] = [$w, $base . $rel . '?v=' . filemtime($root . $rel)];
			continue;
		}
		$canBuild ??= videoFormatCanBuild();
		$failed = $root . $rel . '.failed';
		if ($canBuild && !(is_file($failed) && filemtime($failed) >= filemtime($original))) {
			$out['build'][] = [$w, $base . $rel];
		}
	}
	return $out;
}

/**
 * CMS Upload/Replace: alte Fassungen des Mediums verwerfen (Dateiname kann sich ändern)
 * und im CMS das Fortschritts-Panel starten, das alle Kachel-Fassungen nacheinander erzeugt.
 */
function videoFormatsAfterUpload($media): void {
	$parts = videoFormatMediaParts($media);
	$formats = defined('VIDEO_FORMATS') && is_array(VIDEO_FORMATS) ? array_keys(VIDEO_FORMATS) : [];
	if (!$parts || $formats === []) {
		return;
	}
	$root = videoFormatSiteRoot();
	$base = rtrim((string) (http::$root ?? '/'), '/') . '/';
	$jobs = [];
	foreach ($formats as $format) {
		$config = videoFormatConfig($format);
		if (!$config) {
			continue;
		}
		foreach (glob($root . 'var/formats/' . $format . '/' . $parts['folders'] . '*') ?: [] as $file) {
			if (is_file($file)) {
				@unlink($file);
			}
		}
		$jobs[] = [
			'label' => $parts['filename'],
			'format' => $format,
			'w' => (int) ($config['max_width'] ?? 0),
			'url' => $base . videoFormatRel($format, $parts['folders'], $parts['filename']),
		];
	}
	if (!$jobs || !class_exists('client')) {
		return;
	}
	$script = $base . 'js/videoFormats.cms.js';
	$version = @filemtime($root . 'js/videoFormats.cms.js') ?: time();
	$json = json_encode($jobs, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	client::append('body', '<script>(function(){'
		. '(window.__videoFormatJobs = window.__videoFormatJobs || []).push.apply(window.__videoFormatJobs, ' . $json . ');'
		. 'if (window.videoFormatsCmsRun) { window.videoFormatsCmsRun(); return; }'
		. 'if (window.__videoFormatsCmsLoading) return; window.__videoFormatsCmsLoading = 1;'
		. 'var s = document.createElement("script"); s.src = ' . json_encode($script . '?v=' . $version, JSON_UNESCAPED_SLASHES) . '; document.head.appendChild(s);'
		. '})();</script>');
}

/** "960 url, 1280 url" wie srcset; URLs enthalten weder Leerzeichen noch Kommas. */
function videoFormatSetAttr(array $list): string {
	return implode(', ', array_map(static fn($e) => $e[0] . ' ' . $e[1], $list));
}

/** Läuft ein Bau länger, gilt die Sperre als verwaist (Prozess vom Hoster beendet). */
const VIDEO_FORMAT_LOCK_TTL = 900;

/** Letzte Meldung zum Status für den Header X-Video-Format-Detail. */
function videoFormatDetail(?string $set = null): string {
	static $detail = '';
	if ($set !== null) {
		$detail = $set;
	}
	return $detail;
}

/**
 * ffmpeg im Hintergrund starten, der Request kehrt sofort zurück (kein Proxy-/PHP-Timeout).
 * Serverweit nur ein Bau gleichzeitig. Fehlschläge landen in <ziel>.failed und werden nicht wiederholt,
 * bis das Original neuer ist oder ?rebuild kommt.
 * @return string ok | building | busy | error | missing
 */
function videoFormatBuild(string $format, string $folders, string $filename, bool $force = false): string {
	$config = videoFormatConfig($format);
	if (!$config) {
		videoFormatDetail('Format unbekannt');
		return 'error';
	}
	$root = videoFormatSiteRoot();
	$original = $root . 'var/original/' . $folders . $filename;
	if (!is_file($original)) {
		return 'missing';
	}
	$rel = videoFormatRel($format, $folders, $filename);
	$target = $root . $rel;
	$failed = $target . '.failed';
	$formatDir = $root . 'var/formats/' . $format . '/';
	$lockFile = $formatDir . '.build.lock';

	if (!is_dir($formatDir) && !@mkdir($formatDir, 0777, true)) {
		videoFormatDetail('var/formats nicht beschreibbar');
		return 'error';
	}
	$lock = @fopen($lockFile, 'c+');
	if (!$lock) {
		videoFormatDetail('Sperrdatei nicht beschreibbar');
		return 'error';
	}
	flock($lock, LOCK_EX);
	$job = json_decode((string) stream_get_contents($lock), true);
	$running = is_array($job) && (int) ($job['t'] ?? 0) > time() - VIDEO_FORMAT_LOCK_TTL;
	$release = static function () use ($lock): void {
		flock($lock, LOCK_UN);
		fclose($lock);
	};

	if ($running && ($job['rel'] ?? '') === $rel) {
		videoFormatDetail('läuft seit ' . (time() - (int) $job['t']) . ' s');
		$release();
		return 'building';
	}
	if (!$force && videoFormatIsFresh($target, $original)) {
		$release();
		return 'ok';
	}
	if (!$force && is_file($failed) && filemtime($failed) >= filemtime($original)) {
		videoFormatDetail(trim(str_replace($root, '', (string) @file_get_contents($failed))) ?: 'ffmpeg-Fehler');
		$release();
		return 'error';
	}
	if ($running) {
		videoFormatDetail('erzeugt gerade ' . basename((string) ($job['rel'] ?? '')));
		$release();
		return 'busy';
	}

	$ffmpeg = class_exists('shellBinary', false) ? (string) (\shellBinary::find('ffmpeg') ?? '') : '';
	if ($ffmpeg === '') {
		videoFormatDetail('ffmpeg nicht gefunden (System oder var/bin/ffmpeg)');
		$release();
		return 'error';
	}
	$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
	if (!function_exists('exec') || in_array('exec', $disabled, true)) {
		videoFormatDetail('PHP exec() ist deaktiviert');
		$release();
		return 'error';
	}

	$dir = dirname($target);
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true);
	}
	@unlink($failed);
	$tmp = $target . '.part.mp4';
	$err = $target . '.err';
	$w = (int) ($config['max_width'] ?? 960);
	// Lange Seite auf max_width, Seitenverhältnis bleibt, Maße gerade.
	$scale = "scale=w='if(gte(iw,ih),min($w,iw),-2)':h='if(gte(iw,ih),-2,min($w,ih))'";
	$fps = (int) ($config['fps'] ?? 25);
	$nice = @is_executable('/usr/bin/nice') ? '/usr/bin/nice -n 19 ' : '';
	$ffmpegCmd = implode(' ', [
		$nice . escapeshellarg($ffmpeg),
		'-y -v error -nostdin',
		'-i', escapeshellarg($original),
		'-map 0:v:0 -an -sn -dn',
		'-vf', escapeshellarg("$scale,fps=$fps,format=yuv420p"),
		'-c:v libx264 -preset veryfast -profile:v main -level:v ' . escapeshellarg((string) ($config['level'] ?? '3.1')) . ' -threads 2',
		'-crf', (int) ($config['crf'] ?? 26),
		'-maxrate', escapeshellarg((string) ($config['maxrate'] ?? '1500k')),
		'-bufsize', escapeshellarg((string) ($config['bufsize'] ?? '3000k')),
		'-g', $fps * 2, '-keyint_min', $fps, '-sc_threshold 0',
		'-movflags +faststart',
		'-f mp4', escapeshellarg($tmp),
	]);
	$q = 'escapeshellarg';
	$script = $ffmpegCmd . ' 2> ' . $q($err)
		. ' && [ -s ' . $q($tmp) . ' ] && mv -f ' . $q($tmp) . ' ' . $q($target)
		. ' || { tail -c 300 ' . $q($err) . ' > ' . $q($failed) . '; [ -s ' . $q($failed) . ' ] || echo "ffmpeg abgebrochen" > ' . $q($failed) . ';'
		. ' echo "$(date) ' . $folders . $filename . ': $(tr "\n" " " < ' . $q($failed) . ')" >> ' . $q($formatDir . '.build.log') . '; };'
		. ' rm -f ' . $q($tmp) . ' ' . $q($err) . ' ' . $q($lockFile);

	ftruncate($lock, 0);
	rewind($lock);
	fwrite($lock, json_encode(['rel' => $rel, 't' => time()]));
	fflush($lock);
	$release();

	$output = [];
	$code = 1;
	@exec('nohup sh -c ' . escapeshellarg($script) . ' > /dev/null 2>&1 & echo started', $output, $code);
	if ($code !== 0 || trim(implode('', $output)) !== 'started') {
		@unlink($lockFile);
		videoFormatDetail('Hintergrundprozess ließ sich nicht starten (code ' . $code . ')');
		return 'error';
	}
	videoFormatDetail('gestartet');
	return 'building';
}

/**
 * Anfrage an var/formats/<videoFormat>/… abfangen (Datei fehlt, veraltet oder ?rebuild).
 * HEAD: nur bauen, Ergebnis im Header X-Video-Format. GET: bauen, dann auf die Datei (oder das Original) umleiten.
 */
function videoFormatRoute(): string {
	if (method_exists('http', 'htaccessResult')) {
		$route = trim((string) http::htaccessResult(), '/');
		if ($route !== '') {
			return $route;
		}
	}
	$path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
	$pos = strpos($path, 'var/formats/');
	return $pos === false ? '' : substr($path, $pos);
}

function videoFormatHandleRequest(): void {
	$route = videoFormatRoute();
	if (!preg_match('~^var/formats/([A-Za-z0-9_]+)/(\d{3}/\d{2}/)([^/]+\.mp4)$~', $route, $m)) {
		return;
	}
	[, $format, $folders, $file] = $m;
	if (!videoFormatConfig($format)) {
		return;
	}
	$root = videoFormatSiteRoot();
	$stem = preg_replace('/\.mp4$/i', '', $file);
	$filename = '';
	foreach (['mp4', 'mov', 'm4v', 'webm', 'MP4', 'MOV'] as $ext) {
		if (is_file($root . 'var/original/' . $folders . $stem . '.' . $ext)) {
			$filename = $stem . '.' . $ext;
			break;
		}
	}
	$base = rtrim((string) (http::$root ?? '/'), '/') . '/';
	$force = isset($_GET['rebuild']);
	if ($filename === '') {
		$status = 'missing';
	} elseif (!videoFormatCanBuild()) {
		$status = 'auth';
	} else {
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		$status = videoFormatBuild($format, $folders, $filename, $force);
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Cache-Control: no-store');
	header('X-Video-Format: ' . $status);
	$detail = videoFormatDetail();
	if ($detail !== '') {
		header('X-Video-Format-Detail: ' . rawurlencode(mb_substr($detail, 0, 300)));
	}
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
		$codes = ['ok' => 204, 'building' => 202, 'busy' => 202, 'missing' => 404, 'auth' => 403];
		http_response_code($codes[$status] ?? 500);
		exit;
	}
	if ($status === 'ok') {
		$rel = videoFormatRel($format, $folders, $filename);
		header('Location: ' . $base . $rel . '?v=' . filemtime($root . $rel), true, 302);
		exit;
	}
	if ($filename !== '') {
		header('Location: ' . $base . 'var/original/' . $folders . $filename, true, 302);
		exit;
	}
	http_response_code(404);
	exit;
}
