<?php
/**
 * @var \Systopic\System\Tables\Medias\Media $original
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 */
$videoPath = \fs::toNative($original->path);
$posterPath = preg_replace('/\.[^.]+$/', '.webp', $videoPath);
$posterSrc = preg_replace('/\.[^.]+$/', '.webp', $original->url);
$hasPoster = is_file($posterPath);
$hasVideo = is_file($videoPath);
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$formats = defined('VIDEO_FORMATS') && is_array(VIDEO_FORMATS) ? VIDEO_FORMATS : [];
$parts = function_exists('videoFormatMediaParts') ? videoFormatMediaParts($media) : null;
$formatBase = rtrim((string) http::$root, '/') . '/';
?>
<div class="videoEditor">
	<ol class="ceRatios" id="videoFormat" aria-label="Video format">
		<li><button type="button" class="button selected" aria-pressed="true" value="<?= $e($original->src) ?>" data-format="original" data-ready="1" data-poster="<?= $e($posterSrc) ?>" data-poster-ready="<?= $hasPoster ? '1' : '0' ?>"><span class="ceRatioName">Original</span><span class="ceRatioBox" aria-hidden="true" style="width:1.6rem;height:1rem"></span></button></li>
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
			$label = mediaFormats::label((string) $name, $config);
			$label = $width > 0 ? $label . ' (' . $width . ' px)' : $label;
			?>
			<li><button type="button" class="button" aria-pressed="false" value="<?= $e($url) ?>" data-format="<?= $e($name) ?>" data-ready="<?= $ready ? '1' : '0' ?>" data-poster="<?= $e($posterUrl) ?>" data-poster-ready="<?= $posterReady ? '1' : '0' ?>"><span class="ceRatioName"><?= $e($label) ?></span><span class="ceRatioBox" aria-hidden="true" style="width:1.6rem;height:1rem"></span></button></li>
		<?php } ?>
	</ol>
<div class="videoWorkspace">
<?php if (!$hasVideo) { ?>
	<p class="fileHint">The original file is not on disk.</p>
<?php } else { ?>
	<div class="fileFrame">
		<video id="videosnapshot" data-node_id="<?= (int) $this->targetId ?>" controls playsinline src="<?= $e($original->src) ?>"<?php if ($hasPoster) { ?> poster="<?= $e($posterSrc . '?v=' . filemtime($posterPath)) ?>"<?php } ?>></video>
		<p class="fileHint" data-video-empty hidden>This video format has not been generated yet.</p>
		<button type="button" class="button submit formatCreate" hidden><i class="cmsIcon cmsIcon-film"></i><span>Create format</span></button>
	</div>
	<div class="fileFrame posterPane">
		<canvas id="snapshotpreview" hidden></canvas>
		<img id="videoPosterPreview" alt="" data-src="<?= $e($posterSrc) ?>"<?php if ($hasPoster) { ?> src="<?= $e($posterSrc . '?v=' . filemtime($posterPath)) ?>"<?php } else { ?> hidden<?php } ?>>
		<p class="fileHint" data-poster-empty<?= $hasPoster ? ' hidden' : '' ?>>No poster yet. Choose a frame in the video to create one.</p>
		<button type="button" id="snap" class="button submit posterButton" title="Use the current frame as the poster"><i class="cmsIcon cmsIcon-image"></i>Poster from this frame</button>
	</div>
<?php } ?>
</div>
</div>
<script>
	(function () {
		var video = document.querySelector('video#videosnapshot');
		var format = document.getElementById('videoFormat');
		var create = document.querySelector('.formatCreate');
		function fitVideo() {
			if (!video || !video.videoWidth || !video.videoHeight) return;
			var frame = video.parentElement;
			var style = getComputedStyle(frame);
			var width = frame.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
			var height = frame.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
			var scale = Math.min(width / video.videoWidth, height / video.videoHeight);
			video.style.width = Math.max(0, video.videoWidth * scale) + 'px';
			video.style.height = Math.max(0, video.videoHeight * scale) + 'px';
		}
		if (video) {
			video.addEventListener('loadedmetadata', fitVideo);
			var resize = new ResizeObserver(function () {
				if (!video.isConnected) { resize.disconnect(); return; }
				fitVideo();
			});
			resize.observe(video.parentElement);
			fitVideo();
		}
		function selectedOption() {
			return format ? format.querySelector('button[aria-pressed="true"]') : null;
		}
		function showFormat() {
			var option = selectedOption();
			if (!video || !option) {
				return;
			}
			var poster = document.getElementById('videoPosterPreview');
			var posterSrc = option.getAttribute('data-poster') || '';
			var ready = option.getAttribute('data-ready') !== '0';
			var hasPoster = ready && option.getAttribute('data-poster-ready') === '1' && posterSrc;
			var posterEmpty = document.querySelector('[data-poster-empty]');
			posterEmpty.hidden = !!hasPoster;
			posterEmpty.textContent = ready ? 'No poster yet. Choose a frame in the video to create one.' : 'Create the video format first to choose a poster.';
			document.querySelector('[data-video-empty]').hidden = ready;
			if (poster) {
				poster.setAttribute('data-src', posterSrc);
				if (hasPoster) {
					poster.hidden = false;
					poster.src = posterSrc + (posterSrc.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
				} else {
					poster.hidden = true;
					poster.removeAttribute('src');
				}
			}
			var original = option.getAttribute('data-format') === 'original';
			document.getElementById('snap').disabled = !ready;
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
				video.removeAttribute('src');
				video.removeAttribute('poster');
				video.load();
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
			format.addEventListener('click', function (event) {
				var button = event.target.closest('button[data-format]');
				if (!button || create?.classList.contains('loading') || document.getElementById('snap').classList.contains('loading')) return;
				format.querySelectorAll('button').forEach(function (item) {
				    item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
				    item.classList.toggle('selected', item === button);
				});
				showFormat();
			});
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

		document.getElementById('snap').addEventListener('click', function () {
			var button = this;
			var w = video.videoWidth, h = video.videoHeight;
			if (!w || !h || video.readyState < 2) {
				return;
			}
			button.classList.add('loading');
			canvas.width = w;
			canvas.height = h;
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
				option.setAttribute('data-poster-ready', '1');
				var image = document.getElementById('videoPosterPreview');
				image.hidden = false;
				image.src = image.getAttribute('data-src') + '?v=' + Date.now();
				document.querySelector('[data-poster-empty]').hidden = true;
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
