<div class="fileToolbar">
	<select data-node_id="<?= $this->targetId ?>" data-on_change="changeImageFormat" name="imageEditorFormat" class="put">
		<?php
		// settings.php sets $image_formats at global scope, but panel files are
		// included inside PanelNode::render()/runActions() - a method scope, where
		// the global does not reach. Same source, read locally.
		$image_formats = mediaFormats::get();
		// What the select offers: reversed, minus the formats the cms hides.
		$visibleFormats = array_filter(
			array_reverse($image_formats),
			fn($format) => ($format['cms_display'] ?? '') !== 'none'
		);
		// Which one is marked, decided BEFORE the options are printed: an explicit
		// ?format= wins, then the choice the last change stored on this panel,
		// then the first of the list. Deciding inside the loop also marked the
		// first entry, and knew no choice that did not come from the url - so
		// every re-render showed the first format while the image kept the one
		// the user picked.
		$selectedFormat = (string) http::get('format', $this->state->format ?? '');
		if (!isset($visibleFormats[$selectedFormat])) {
			$selectedFormat = (string) array_key_first($visibleFormats);
		}
		foreach ($visibleFormats as $key => $image_format) {
			if (isset($image_format['width'])) {
				$format_info = $image_format['width'] . ' x ' . $image_format['height'] . 'px';
			} elseif (isset($image_format['autocrop'])) {
				$autocrop = $image_format["autocrop_limit"];
				$format_info = 'Orientation: ' . $image_format['autocrop'] . '&nbsp&nbsp;&mdash;&nbsp;&nbsp;Aspect Ratio: ' . $autocrop;
			} else {
				// settype() returns TRUE, not the value - this read 'Megapixels: 1'
				// for every format that is sized by megapixels.
				$format_info = 'Megapixels: ' . $image_format['megapixels'];
			}
			?>
			<option value="<?= $key ?>"<?= $selectedFormat === $key ? ' selected' : '' ?>><?= $key ?> (<?= $format_info ?>)</option>
			<?php
		}
		?>
	</select>
</div>
<div class="fileAdjust" data-save="<?= htmlspecialchars(rtrim((string) http::$root, '/') . '/' . ltrim($this->getPath(), '/'), ENT_QUOTES, 'UTF-8') ?>">
	<label><span>Brightness</span><output>0</output><input type="range" min="0" max="200" value="100" data-adjust="brightness"></label>
	<label><span>Contrast</span><output>0</output><input type="range" min="0" max="200" value="100" data-adjust="contrast"></label>
	<label><span>Saturation</span><output>0</output><input type="range" min="0" max="200" value="100" data-adjust="saturation"></label>
	<div class="fileAdjustActions">
		<button type="button" data-rotate="-90" title="Rotate left"><i class="fa-regular fa-rotate-left"></i></button>
		<button type="button" data-rotate="90" title="Rotate right"><i class="fa-regular fa-rotate-right"></i></button>
		<button type="button" data-flip title="Flip"><i class="fa-regular fa-reflect-horizontal"></i></button>
		<button type="button" data-reset-adjust title="Reset adjustments"><i class="fa-regular fa-arrow-rotate-left"></i></button>
		<button type="button" data-restore-original title="Restore original"><i class="fa-regular fa-clock-rotate-left"></i></button>
		<button type="button" class="button cropButton" data-on_click="startCroping"><i class="fa-regular fa-crop-simple"></i>Crop</button>
		<div class="splitApply">
			<button type="button" class="button submit" data-apply-adjust><i class="fa-regular fa-check"></i>Apply</button>
			<button type="button" class="button submit splitApplyMenu" title="Apply options"><i class="fa-regular fa-chevron-down"></i></button>
			<div class="splitApplyList" hidden>
				<button type="button" data-apply-adjust data-apply-all="1">Apply to all formats</button>
			</div>
		</div>
	</div>
</div>
<div class="fileFrame imageCropAlignContainer">
	<div class="frameZoom">
		<button type="button" data-view-zoom="0.8" title="Zoom out"><i class="fa-regular fa-minus"></i></button>
		<button type="button" data-view-zoom="reset" title="Reset zoom"><i class="fa-regular fa-expand"></i></button>
		<button type="button" data-view-zoom="1.25" title="Zoom in"><i class="fa-regular fa-plus"></i></button>
	</div>
	<div class="frameView">
		<img class="srcToChange" data-on_click="startCroping" alt="" src="<?= htmlspecialchars($media->setFormat($selectedFormat)->src, ENT_QUOTES, 'UTF-8') ?>">
	</div>
</div>
<script>
	(function () {
		var img = document.querySelector('img.srcToChange');
		var bar = document.querySelector('.fileAdjust');
		if (!img || !bar) {
			return;
		}
		var frame = img.closest('.fileFrame');
		var view = img.closest('.frameView');
		var zoom = 1;
		var state = {brightness: 100, contrast: 100, saturation: 100, rotate: 0, flip: 1};
		function paint() {
			img.style.filter = 'brightness(' + state.brightness + '%) contrast(' + state.contrast + '%) saturate(' + state.saturation + '%)';
			img.style.transform = 'rotate(' + state.rotate + 'deg) scaleX(' + state.flip + ')';
			if (view) {
				view.style.transform = zoom === 1 ? '' : 'scale(' + zoom + ')';
			}
		}
		function fitBox() {
			if (!view || !frame || !img.naturalWidth || !img.naturalHeight) {
				return;
			}
			var maxW = frame.clientWidth;
			var maxH = frame.clientHeight;
			if (!maxW || !maxH) {
				return;
			}
			var scale = Math.min(maxW / img.naturalWidth, maxH / img.naturalHeight, 1);
			var nextW = Math.max(1, Math.round(img.naturalWidth * scale));
			var nextH = Math.max(1, Math.round(img.naturalHeight * scale));
			if (view.style.width === nextW + 'px' && view.style.height === nextH + 'px') {
				return;
			}
			view.style.width = nextW + 'px';
			view.style.height = nextH + 'px';
		}
		function viewZoom(factor) {
			var next = zoom * factor;
			if (next < 0.25 || next > 6) {
				return;
			}
			zoom = next;
			paint();
		}
		if (frame) {
			frame.addEventListener('click', function (event) {
				var button = event.target.closest('[data-view-zoom]');
				if (!button) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				if (button.getAttribute('data-view-zoom') === 'reset') {
					zoom = 1;
					paint();
					return;
				}
				viewZoom(parseFloat(button.getAttribute('data-view-zoom')) || 1);
			});
			frame.addEventListener('wheel', function (event) {
				event.preventDefault();
				viewZoom(event.deltaY < 0 ? 1.1 : 1 / 1.1);
			}, {passive: false});
		}
		function neutral() {
			return state.brightness === 100 && state.contrast === 100 && state.saturation === 100 && state.rotate === 0 && state.flip === 1;
		}
		function clearAdjustments() {
			state = {brightness: 100, contrast: 100, saturation: 100, rotate: 0, flip: 1};
			bar.querySelectorAll('input[data-adjust]').forEach(function (field) {
				field.value = '100';
				readout(field);
			});
			paint();
		}
		function showOriginal(url, button) {
			var preload = new Image();
			preload.onload = function () {
				img.addEventListener('load', function once() {
					img.removeEventListener('load', once);
					fitBox();
					clearAdjustments();
					if (button) {
						button.classList.remove('loading');
					}
				});
				img.src = url;
			};
			preload.onerror = function () {
				if (button) {
					button.classList.remove('loading');
				}
			};
			preload.src = url;
		}
		function readout(field) {
			var out = field.parentElement.querySelector('output');
			if (!out) {
				return;
			}
			var n = Number(field.value) - 100;
			out.textContent = (n > 0 ? '+' : '') + n;
		}
		bar.addEventListener('input', function (event) {
			var key = event.target.getAttribute('data-adjust');
			if (!key) {
				return;
			}
			state[key] = Number(event.target.value);
			readout(event.target);
			paint();
		});
		function closeMenu() {
			if (typeof closeSplitMenus === 'function') {
				closeSplitMenus();
			}
		}
		function saveAction(name, params) {
			var base = bar.getAttribute('data-save') || '';
			return fetch(base + name + '?' + params.toString(), {credentials: 'same-origin'});
		}
		function applyAdjust(chosen) {
			if (!chosen || neutral()) {
				return;
			}
			chosen.classList.add('loading');
			closeMenu();
			var formatField = document.querySelector('select[name="imageEditorFormat"]');
			var adjust = new URLSearchParams(location.search);
			adjust.set('format', formatField ? formatField.value : '');
			adjust.set('apply_all', chosen.getAttribute('data-apply-all') === '1' ? '1' : '0');
			adjust.set('brightness', String(state.brightness));
			adjust.set('contrast', String(state.contrast));
			adjust.set('saturation', String(state.saturation));
			adjust.set('rotate', String(state.rotate));
			adjust.set('flip', state.flip < 0 ? '1' : '0');
			adjust.set('ajax', '1');
			saveAction('adjustImage', adjust).then(function (res) {
				if (!res.ok || res.headers.get('X-Adjust') !== 'saved') {
					chosen.classList.remove('loading');
					return;
				}
				var originalButton = bar.querySelector('[data-restore-original]');
				if (originalButton) {
					originalButton.hidden = false;
				}
				showOriginal(img.src.split('?')[0] + '?rebuild=' + Date.now(), chosen);
			});
		}
		var menuList = bar.querySelector('.splitApplyList');
		if (menuList) {
			menuList.addEventListener('click', function (event) {
				var chosen = event.target.closest('[data-apply-adjust]');
				if (!chosen) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				applyAdjust(chosen);
			});
		}
		bar.addEventListener('click', function (event) {
			var rotate = event.target.closest('[data-rotate]');
			if (rotate) {
				state.rotate = (state.rotate + Number(rotate.getAttribute('data-rotate')) + 360) % 360;
				paint();
				return;
			}
			if (event.target.closest('[data-flip]')) {
				state.flip = state.flip === 1 ? -1 : 1;
				paint();
				return;
			}
			if (event.target.closest('[data-reset-adjust]')) {
				clearAdjustments();
				return;
			}
			var restore = event.target.closest('[data-restore-original]');
			if (restore) {
				restore.classList.add('loading');
				var formatField = document.querySelector('select[name="imageEditorFormat"]');
				var back = new URLSearchParams({
					format: formatField ? formatField.value : '',
					apply_all: 0,
					ajax: 1
				});
				saveAction('restoreOriginal', back).then(function (res) {
					if (!res.ok) {
						restore.classList.remove('loading');
						return;
					}
					showOriginal(img.src.split('?')[0] + '?rebuild=' + Date.now(), restore);
				});
				return;
			}
			applyAdjust(event.target.closest('[data-apply-adjust]'));
		});
		img.addEventListener('load', fitBox);
		if (img.complete) {
			fitBox();
		}
		if (frame && window.ResizeObserver) {
			new ResizeObserver(fitBox).observe(frame);
		}
	})();
</script>
