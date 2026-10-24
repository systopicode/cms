<?php
/**
 * @var \Systopic\System\Tables\Medias\Media $original
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Editor\File\Panel $this
 */
$videoPath = \fs::toNative($original->path);
$posterPath = preg_replace('/\.mp4$/i', '', $videoPath) . '.webp';
$posterSrc = preg_replace('/\.mp4$/i', '', $original->url) . '.webp';
$hasPoster = is_file($posterPath);
$hasVideo = is_file($videoPath);
$aspect = $media->width && $media->height ? $media->width / $media->height : '16/9';
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$formats = defined('VIDEO_FORMATS') && is_array(VIDEO_FORMATS) ? VIDEO_FORMATS : [];
$parts = function_exists('videoFormatMediaParts') ? videoFormatMediaParts($media) : null;
$formatBase = rtrim((string) http::$root, '/') . '/';
?>
<div class="fileToolbar">
	<select id="videoFormat">
		<option value="<?= $e($original->src) ?>" data-format="original" data-ready="1" data-poster="<?= $e($posterSrc) ?>"<?= $hasPoster ? '' : ' data-poster-ready="0"' ?>>Original</option>
		<?php foreach ($formats as $name => $config) {
			if (!$parts || !function_exists('videoFormatRel')) {
				continue;
			}
			$rel = videoFormatRel((string) $name, $parts['folders'], $parts['filename']);
			$url = $formatBase . $rel;
			$posterRel = preg_replace('/\.mp4$/', '.webp', $rel);
			$posterUrl = $formatBase . $posterRel;
			$ready = is_file(\fs::toNative((string) \fs::$siteRoot . $rel));
			$posterReady = is_file(\fs::toNative((string) \fs::$siteRoot . $posterRel));
			$width = (int) ($config['max_width'] ?? 0);
			$label = $width > 0 ? $name . ' (' . $width . ' px)' : (string) $name;
			?>
			<option value="<?= $e($url) ?>" data-format="<?= $e($name) ?>" data-ready="<?= $ready ? '1' : '0' ?>" data-poster="<?= $e($posterUrl) ?>" data-poster-ready="<?= $posterReady ? '1' : '0' ?>"><?= $e($label) ?></option>
		<?php } ?>
	</select>
</div>
<?php if (!$hasVideo) { ?>
	<p class="fileHint">The original file is not on disk.</p>
<?php } else { ?>
	<div class="fileFrame">
		<video id="videosnapshot" data-node_id="<?= (int) $this->targetId ?>" controls playsinline src="<?= $e($original->src) ?>"<?php if ($hasPoster) { ?> poster="<?= $e($posterSrc . '?v=' . filemtime($posterPath)) ?>"<?php } ?>></video>
		<button type="button" class="button submit formatCreate" hidden><i class="fa-regular fa-film"></i><span>Create format</span></button>
	</div>
	<div class="fileFrame posterPane">
		<canvas id="snapshotpreview" hidden></canvas>
		<img id="videoPosterPreview" alt="" data-src="<?= $e($posterSrc) ?>"<?php if ($hasPoster) { ?> src="<?= $e($posterSrc . '?v=' . filemtime($posterPath)) ?>"<?php } else { ?> hidden<?php } ?>>
		<button type="button" id="snap" class="button submit posterButton" title="Use the current frame as the poster"><i class="fa-regular fa-image"></i>Poster from this frame</button>
	</div>
<?php } ?>
<script>
	(function () {
		var video = document.querySelector('video#videosnapshot');
		var format = document.getElementById('videoFormat');
		var create = document.querySelector('.formatCreate');
		function selectedOption() {
			return format ? format.options[format.selectedIndex] : null;
		}
		function showFormat() {
			var option = selectedOption();
			if (!video || !option) {
				return;
			}
			var poster = document.getElementById('videoPosterPreview');
			var posterSrc = option.getAttribute('data-poster') || '';
			if (poster) {
				poster.setAttribute('data-src', posterSrc);
				if (option.getAttribute('data-poster-ready') === '1' && posterSrc) {
					poster.hidden = false;
					poster.src = posterSrc + (posterSrc.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
				} else {
					poster.hidden = true;
					poster.removeAttribute('src');
				}
			}
			var ready = option.getAttribute('data-ready') !== '0';
			var original = option.getAttribute('data-format') === 'original';
			if (create) {
				create.hidden = original;
				var label = create.querySelector('span');
				if (label) {
					label.textContent = ready ? 'Recreate' : 'Create format';
				}
			}
			video.classList.toggle('is-hidden', !ready);
			if (!ready) {
				video.pause();
				return;
			}
			video.pause();
			video.src = option.value;
			video.load();
		}
		if (format) {
			showFormat();
		}
		if (video && format) {
			format.addEventListener('change', showFormat);
		}
		if (create && video && format) {
			create.addEventListener('click', function () {
				var option = selectedOption();
				if (!option) {
					return;
				}
				create.classList.add('loading');
				var readyNow = option.getAttribute('data-ready') !== '0';
				var url = option.value.split('?')[0];
				if (readyNow) {
					url += '?rebuild=1';
				}
				function poll() {
					fetch(url, {method: 'HEAD', cache: 'no-store', credentials: 'same-origin'}).then(function (res) {
						var status = res.headers.get('X-Video-Format');
						if (status === 'ok' || (res.ok && res.status !== 202)) {
							option.setAttribute('data-ready', '1');
							if (readyNow) {
								option.value = option.value.split('?')[0] + '?v=' + Date.now();
							}
							create.classList.remove('loading');
							showFormat();
							return;
						}
						if (!status || status === 'error' || status === 'missing' || status === 'auth') {
							create.classList.remove('loading');
							return;
						}
						window.setTimeout(poll, 2000);
					}).catch(function () {
						create.classList.remove('loading');
					});
				}
				poll();
			});
		}
		var canvas = document.querySelector('canvas#snapshotpreview');
		if (!video || !canvas) {
			return;
		}
		var context = canvas.getContext('2d');
		var w = 0;
		var h = 0;
		video.addEventListener('loadedmetadata', function () {
			w = video.videoWidth;
			h = video.videoHeight;
			canvas.width = w;
			canvas.height = h;
		});
		document.getElementById('snap').addEventListener('click', function () {
			var button = this;
			if (!w || !h) {
				return;
			}
			button.classList.add('loading');
			context.drawImage(video, 0, 0, w, h);
			var xhr = new XMLHttpRequest();
			var option = selectedOption();
			var formatName = option ? (option.getAttribute('data-format') || 'original') : 'original';
			xhr.open('POST', 'createPoster?target_id=' + video.getAttribute('data-node_id') + '&format=' + encodeURIComponent(formatName), true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.onload = function () {
				button.classList.remove('loading');
				if (xhr.status !== 200) {
					return;
				}
				var image = document.getElementById('videoPosterPreview');
				image.hidden = false;
				image.src = image.getAttribute('data-src') + '?v=' + Date.now();
			};
			xhr.onerror = function () {
				button.classList.remove('loading');
			};
			xhr.send('value=' + encodeURIComponent(canvas.toDataURL('image/webp')));
		});
	})();
</script>
<style>
	canvas#snapshotpreview{
		position: absolute;
		opacity: 0;
		visibility: hidden;
	}
</style>
