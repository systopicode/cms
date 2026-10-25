/* global sys,c,p,t,l */
(function () {
	var setup = {
		info: 'upload panel class extends base panel class', // debug
		route: 'panels.cms.pages.upload',
		extends: 'classes.panelNode', // => baseclass
		spanSize: 3 * 1024 * 1024 // send uploads in x MB packages
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup);
		var upload = this;
		upload.info = {};

		upload.targetOf = (el) => {
			var groupLi = el.closest('li.title');
			var root = el.closest('[data-node_parent_node_id]');
			var mediaLi = el.closest('li[data-node_id]');
			var target = {
				node_parent_node_id: root ? root.getAttribute('data-node_parent_node_id') : '',
				group: groupLi ? groupLi.getAttribute('data-media_group') : ''
			};
			if (mediaLi && !mediaLi.classList.contains('upload') && !mediaLi.classList.contains('title')) {
				target.node_id = mediaLi.getAttribute('data-node_id');
			}
			return target;
		};

		upload.openLightbox = (get, file, nodeId) => {
			if (nodeId) {
				get.node_id = nodeId;
			}
			upload.pendingFile = file || null;
			sys.core.server.sendGet('upload/', get).done(() => {
				upload.consumePendingFile();
			});
		};

		upload.consumePendingFile = () => {
			if (!upload.pendingFile || !upload.doc || !upload.doc.box) {
				return;
			}
			upload.fileInfo = upload.pendingFile;
			upload.pendingFile = null;
			upload.readDroppedFile();
		};

		// The preview points at the file itself - read into a data url, a large video stalls the page.
		upload.readDroppedFile = () => {
			if (upload.src && upload.src.indexOf('blob:') === 0) {
				URL.revokeObjectURL(upload.src);
			}
			upload.src = URL.createObjectURL(upload.fileInfo);
			upload.preview();
		};

		upload.inputOf = (event) => {
			var candidates = [
				event.sourceElement,
				event.element,
				event.e && event.e.target,
				event.e && event.e.currentTarget
			];
			for (var i = 0; i < candidates.length; i++) {
				var el = candidates[i];
				if (el && el.jquery) {
					el = el[0];
				}
				if (el && el.files) {
					return el;
				}
			}
			return null;
		};

		upload.takeMany = (input) => {
			if (!input || !input.files || input.files.length < 2) {
				return false;
			}
			if (Date.now() - (upload.dropStamp || 0) < 800) {
				input.value = '';
				return true;
			}
			var files = Array.prototype.slice.call(input.files);
			var spinner = input.closest('li.upload') || input.closest('li');
			var target = upload.targetOf(input);
			delete target.node_id;
			input.value = '';
			upload.queueDirect(files, target, spinner);
			return true;
		};

		upload.open = (event) => {
			upload.event = event;
			var input = upload.inputOf(event);
			if (upload.takeMany(input)) {
				return;
			}
			var get = {
				node_parent_node_id: event.data.dataset.node_parent_node_id,
				group: event.data.dataset.media_group
			}
			if (event.data.dataset.node_id) {
				get.node_id = event.data.dataset.node_id;
			}
			sys.core.server.sendGet('upload/', get).done(() => {
				upload.readFileFromInput(event.element);
			});
		};

		// Several files, or a file dropped onto a tile: no lightbox. The plus
		// (or the tile) shows a spinner until the last file has been stored.
		/** Shows the new files: the library alone is drawn again, and only if the page is still open. */
		upload.refreshLibrary = (from) => {
			if (location.pathname + location.search !== from) {
				return;
			}
			var url = new URL('mediaAdded', location.origin + from);
			var call = new sys.core.server.call();
			call.data.method = 'put';
			call.send(url.pathname + new URL(location.origin + from).search);
		};

		upload.reportQueue = (done, total, el, part) => {
			if (!window.cmsJobs) {
				return;
			}
			if (done >= total) {
				cmsJobs.clear('upload');
				return;
			}
			cmsJobs.update('upload', {title: 'Uploading', done: done, total: total, el: el, progress: (done + (part || 0)) / total});
		};

		/**
		 * What a drop or paste brings along: its files, else the addresses of
		 * the images and videos in it (dragged out of another browser window).
		 */
		upload.mediaIn = (transfer) => {
			var files = Array.prototype.slice.call((transfer && transfer.files) || []);
			if (files.length) {
				return files;
			}
			var urls = [];
			var html = transfer.getData ? transfer.getData('text/html') : '';
			if (html) {
				new DOMParser().parseFromString(html, 'text/html').querySelectorAll('img[src], video[src], video source[src]').forEach((el) => {
					urls.push(el.getAttribute('src'));
				});
			}
			(transfer.getData ? transfer.getData('text/uri-list') : '').split(/\r?\n/).forEach((line) => {
				if (/\.(jpe?g|png|gif|webp|avif|svg|mp4|m4v|mov|webm)(\?|#|$)/i.test(line.trim())) {
					urls.push(line.trim());
				}
			});
			return urls.filter((url, i) => /^https?:\/\//i.test(url) && urls.indexOf(url) === i).map((url) => ({url: url}));
		};

		upload.fetchUrl = (url, target, queue, extra) => {
			return new Promise((resolve, reject) => {
				var call = new sys.core.server.call(Object.assign({url: url, queue: !!queue}, extra || {}));
				call.data.get = {node_parent_node_id: target.node_parent_node_id, group: target.group || ''};
				call.data.method = 'put';
				call.send(new URL('upload/fetch', location.origin + (target.from || location.pathname)).pathname).done((data) => {
					if (data && data.error) {
						alert(data.error);
						reject();
						return;
					}
					resolve(data);
				}).fail(reject);
			});
		};

		upload.send = (item, target, queue, queueLast, extra, onPart) => {
			return item instanceof Blob
				? upload.sendFileDirect(item, target, queue, queueLast, extra, onPart)
				: upload.fetchUrl(item.url, target, queue, extra);
		};

		upload.queueDirect = (files, target, spinner) => {
			var list = Array.prototype.slice.call(files);
			if (!list.length || !spinner) {
				return;
			}
			spinner.classList.add('loading');
			upload.reportQueue(0, list.length, spinner);
			var from = target.from = location.pathname + location.search;
			var step = (index) => {
				var last = index === list.length - 1;
				var item = list[index];
				var sent = item instanceof Blob
					? upload.sendFileDirect(item, target, true, last, null, (part) => upload.reportQueue(index, list.length, spinner, part))
					: upload.fetchUrl(item.url, target, true);
				sent.then(() => {
					// files redraw after the last one themselves (sendFileDirect)
					if (last && !(item instanceof Blob)) {
						upload.refreshLibrary(from);
					}
					upload.reportQueue(index + 1, list.length, spinner);
					if (!last) {
						step(index + 1);
						return;
					}
					spinner.classList.remove('loading');
				}).catch(() => {
					spinner.classList.remove('loading');
					if (window.cmsJobs) {
						cmsJobs.clear('upload');
					}
				});
			};
			step(0);
		};

		// One slice in memory at a time: a whole video read at once stalls the page.
		upload.sendFileDirect = (file, target, queue, queueLast, extra, onPart) => {
			return new Promise((resolve, reject) => {
				var size = file.size;
				var spanSize = setup.spanSize;
				var spanCount = Math.max(1, Math.ceil(size / spanSize));
				var spanIndex = 0;
				var name = file.name || ('pasted.' + (file.type.split('/')[1] || 'bin').replace(/\W.*$/, ''));
				var label = name.replace(/\.[^.]+$/, '');
				// fixed for the whole queue: a relative address would follow the user to another panel
				var from = target.from || (target.from = location.pathname + location.search);
				var address = new URL('upload/upload', location.origin + from).pathname;
				var sendSpan = () => {
					var start = spanIndex * spanSize;
					var last = spanIndex + 1 >= spanCount;
					var end = last ? size : start + spanSize;
					var reader = new FileReader();
					reader.onerror = () => reject(reader.error);
					reader.onload = () => {
						var call = new sys.core.server.call(Object.assign({
							name: name,
							label: label,
							info: {},
							size: size,
							spanIndex: spanIndex,
							spanSize: end - start,
							lastSpan: last,
							queue: !!queue,
							queueLast: !!(queue && last && queueLast)
						}, extra || {}));
						call.data.get = {
							node_parent_node_id: target.node_parent_node_id,
							group: target.group || ''
						};
						if (target.node_id) {
							call.data.get.node_id = target.node_id;
						}
						call.data.method = 'put';
						call.binary = reader.result;
						call.send(address).done((data) => {
							onPart && onPart((spanIndex + 1) / spanCount);
							if (!last) {
								spanIndex++;
								sendSpan();
								return;
							}
							resolve(data);
							if (queue && queueLast) {
								upload.refreshLibrary(from);
							}
						}).fail(reject);
					};
					reader.readAsArrayBuffer(file.slice(start, end));
				};
				sendSpan();
			});
		};

		upload.bindFileDrop = () => {
			if (upload.fileDropBound) {
				return;
			}
			upload.fileDropBound = true;
			var depth = 0;
			// a drag that started on this page (an image moved within a text) is the browser's
			var innerDrag = false;
			document.addEventListener('dragstart', () => {
				innerDrag = true;
			}, true);
			document.addEventListener('dragend', () => {
				innerDrag = false;
			}, true);
			var carriesFiles = (event) => {
				var types = Array.prototype.slice.call((event.dataTransfer && event.dataTransfer.types) || []);
				return types.indexOf('Files') !== -1
					|| (!innerDrag && (types.indexOf('text/uri-list') !== -1 || types.indexOf('text/html') !== -1));
			};
			document.addEventListener('click', (event) => {
				var li = event.target && event.target.closest && event.target.closest('li.upload[data-upload-many]');
				if (!li) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				var picker = document.createElement('input');
				picker.type = 'file';
				picker.multiple = true;
				picker.style.cssText = 'position:fixed;left:-100px;top:0;width:1px;height:1px;opacity:0';
				document.body.appendChild(picker);
				picker.addEventListener('change', () => {
					var files = Array.prototype.slice.call(picker.files || []);
					picker.remove();
					if (!files.length) {
						return;
					}
					var target = upload.targetOf(li);
					delete target.node_id;
					if (files.length > 1) {
						upload.queueDirect(files, target, li);
						return;
					}
					upload.openLightbox({
						node_parent_node_id: target.node_parent_node_id,
						group: target.group
					}, files[0]);
				});
				picker.click();
			}, true);
			document.addEventListener('dragenter', (event) => {
				if (!carriesFiles(event)) {
					return;
				}
				depth++;
				document.body.classList.add('fileDrag');
			});
			document.addEventListener('dragleave', (event) => {
				if (!carriesFiles(event)) {
					return;
				}
				depth--;
				if (depth <= 0) {
					depth = 0;
					document.body.classList.remove('fileDrag');
				}
			});
			var dropHost = (event) => {
				if (!event.target || !event.target.closest) {
					return null;
				}
				return event.target.closest('div.mmlContent');
			};
			var markDrop = (event) => {
				document.querySelectorAll('div.mmlContent li.fileOver').forEach((el) => {
					el.classList.remove('fileOver');
				});
				if (!event.target || !event.target.closest) {
					return null;
				}
				var tile = event.target.closest('li[data-node_id]:not(.title):not(.upload)');
				var plus = event.target.closest('li.upload');
				var hit = tile || plus;
				if (hit) {
					hit.classList.add('fileOver');
				}
				return {tile: tile, plus: plus, group: event.target.closest('li.title')};
			};
			document.addEventListener('dragover', (event) => {
				if (!carriesFiles(event) || !dropHost(event)) {
					return;
				}
				event.preventDefault();
				if (event.dataTransfer) {
					event.dataTransfer.dropEffect = 'copy';
				}
				markDrop(event);
			}, true);
			document.addEventListener('drop', (event) => {
				depth = 0;
				document.body.classList.remove('fileDrag');
				document.querySelectorAll('div.mmlContent li.fileOver').forEach((el) => {
					el.classList.remove('fileOver');
				});
				if (!carriesFiles(event) || !dropHost(event)) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				upload.dropStamp = Date.now();
				var files = upload.mediaIn(event.dataTransfer);
				if (!files.length) {
					return;
				}
				var tile = event.target.closest('li[data-node_id]:not(.title):not(.upload)');
				var plus = event.target.closest('li.upload');
				var group = event.target.closest('li.title');
				var where = {tile: tile, plus: plus, group: group};
				var host = where.plus || (where.group && where.group.querySelector('li.upload'));
				if (files.length === 1 && files[0] instanceof Blob) {
					var el = where.tile || host;
					if (!el) {
						return;
					}
					var single = upload.targetOf(el);
					if (!where.tile) {
						delete single.node_id;
					}
					upload.openLightbox({
						node_parent_node_id: single.node_parent_node_id,
						group: single.group
					}, files[0], single.node_id);
					return;
				}
				if (!host) {
					return;
				}
				var target = upload.targetOf(host);
				delete target.node_id;
				upload.queueDirect(files, target, host);
			}, true);

			// Into a text: on window, ahead of the editor's own drop and paste
			// handling, which drops images (HTMLeditor.format.js).
			var editorOf = (el) => el && el.closest && el.closest('article.cmsContent [contenteditable="true"][data-on_drop="insertMediaInEditor"]');
			var showable = (item) => !(item instanceof Blob) || /^(image|video)\/|^application\/pdf$/.test(item.type);
			window.addEventListener('drop', (event) => {
				var editor = editorOf(event.target);
				if (!editor || innerDrag) {
					return;
				}
				var items = upload.mediaIn(event.dataTransfer).filter(showable);
				if (!items.length) {
					return;
				}
				event.preventDefault();
				event.stopImmediatePropagation();
				depth = 0;
				document.body.classList.remove('fileDrag');
				upload.intoEditor(editor, upload.rangeAt(editor, event.clientX, event.clientY), items);
			}, true);
			window.addEventListener('paste', (event) => {
				var editor = editorOf(event.target);
				var items = editor && event.clipboardData ? upload.mediaIn({files: event.clipboardData.files}).filter(showable) : [];
				if (!items.length) {
					return;
				}
				event.preventDefault();
				event.stopImmediatePropagation();
				var selection = getSelection();
				var range = selection.rangeCount && editor.contains(selection.getRangeAt(0).startContainer)
					? selection.getRangeAt(0)
					: upload.rangeAt(editor);
				range.deleteContents();
				upload.intoEditor(editor, range, items);
			}, true);
		};

		/** The caret spot under the pointer, inside the editor - else its end. */
		upload.rangeAt = (editor, x, y) => {
			var range = null;
			if (x !== undefined && document.caretRangeFromPoint) {
				range = document.caretRangeFromPoint(x, y);
			} else if (x !== undefined && document.caretPositionFromPoint) {
				var spot = document.caretPositionFromPoint(x, y);
				if (spot) {
					range = document.createRange();
					range.setStart(spot.offsetNode, spot.offset);
				}
			}
			if (range && editor.contains(range.startContainer)) {
				range.collapse(true);
				return range;
			}
			range = document.createRange();
			range.selectNodeContents(editor);
			range.collapse(false);
			return range;
		};

		/**
		 * Files (or addresses) for a text: each one gets a placeholder where it
		 * was dropped, goes into the page's library (group "text") and then
		 * takes the placeholder's place - the editor saves as after typing.
		 */
		upload.intoEditor = (editor, range, items) => {
			var target = {node_parent_node_id: editor.getAttribute('data-node_id'), group: 'text'};
			var marks = items.map((item) => {
				var mark = document.createElement('span');
				mark.className = 'cmsOwn cmsUploading';
				mark.contentEditable = 'false';
				mark.textContent = item.name || item.url.split('/').pop().split('?')[0] || 'media';
				range.insertNode(mark);
				range.setStartAfter(mark);
				range.collapse(true);
				return mark;
			});
			var report = (done, part) => upload.reportQueue(done, items.length, marks[done], part);
			var from = target.from = location.pathname + location.search;
			report(0);
			var step = (index) => {
				if (index >= items.length) {
					report(index);
					// the library shows the new files once the text is saved (its input is debounced)
					setTimeout(() => sys.core.server.onIdle(() => upload.refreshLibrary(from)), 800);
					return;
				}
				var mark = marks[index];
				var settle = (data) => {
					if (mark.isConnected && data && data.tag) {
						mark.insertAdjacentHTML('afterend', data.tag);
					}
					mark.remove();
					if (window.wysiwyg && wysiwyg.changed) {
						wysiwyg.changed(editor);
					} else {
						editor.dispatchEvent(new Event('input', {bubbles: true}));
					}
					report(index + 1);
					step(index + 1);
				};
				upload.send(items[index], target, true, false, {editor: true}, (part) => report(index, part)).then(settle, () => settle(null));
			};
			step(0);
		};
		upload.bindFileDrop();

		upload.onUpdateLightbox = doc => {
			doc.input.onchange = () => {
				upload.readFileFromInput(doc.input);
			};
			doc.label.oninput = () => {
				upload.labelModified = true;
				upload.updateFileInfo();
			};
			doc.label.onfocus = () => {
				setTimeout(() => {
					document.execCommand('selectAll', false, null);
				}, 100);

			};
			doc.upload.onclick = () => {
				upload.uploadFile();
			};
		};

		upload.readFileFromInput = (input) => {
			upload.fileInfo = input.files[0];
			upload.readDroppedFile();
		};

		upload.preview = () => {
			upload.resetPreviews();
			upload.doc.label.value = '';
			upload.doc.name.value = '';
			switch (upload.fileInfo.type) {
				case 'image/jpeg':
				case 'image/svg+xml':
				case 'image/png':
				case 'image/webp':
					upload.updatePreviewImage(() => {
						upload.updateFileInfo();
					});
					break;
				case 'application/pdf':
					upload.updatePreviewPdf(() => {
						upload.updateFileInfo();
					});
					break;
				case 'video/mp4':
				case 'video/quicktime':
					upload.updatePreviewVideo(() => {
						upload.updateFileInfo();
					});
					break;
				default:
					l('unknown:', upload.fileInfo.type);
					upload.updateFileInfo();
			}
		};

		upload.updateFileInfo = () => {
			var mimes = sys.classes.media.info.__data.mimetypes; // untested
			if (!mimes[upload.fileInfo.type]) {
				alert('Filetype not supported:' + upload.fileInfo.type);
				upload.doc.label.value = '';
				upload.doc.name.value = '';
				upload.resetPreviews();
				return;
			}
			var safeNameSettings = sys.singletons.str.__data.safeNameSettings;
			var ext = mimes[upload.fileInfo.type];
			var basename = upload.fileInfo.name.replace(/\..*$/, '');
			if (upload.doc.label.value.length) {
				basename = upload.doc.label.value;
			}

			var safename = basename; // --> to str.js
			for (var i in safeNameSettings.search) {
				safename = safename.replace(new RegExp(safeNameSettings.search[i], 'g'), safeNameSettings.replace[i]);
			}
			var trimregex = '^[' + safeNameSettings.trim + ']+|[' + safeNameSettings.trim + ']+$';
			safename = safename.replace(new RegExp(trimregex, 'g'), '');

			upload.doc.name.value = safename + '.' + ext;
			if (!upload.labelModified) {
				upload.doc.label.value = basename;
			}
			// l(upload.fileInfo);

			var size = printFileSize(upload.fileInfo.size);
			var format = upload.info.width
				? upload.info.width + ' × ' + upload.info.height + ' px · ' + size
				: size;
			var text = function (value) {
				return String(value).replace(/[&<>"']/g, function (c) {
					return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
				});
			};
			upload.doc.info.innerHTML = '<div><dt>Format</dt><dd>' + text(format) + '</dd></div>'
				+ '<div><dt>Type</dt><dd>' + text(upload.fileInfo.type) + '</dd></div>';
			upload.doc.box.className = 'preview';
			upload.doc.footer.className = 'preview';
		};

		// the lightbox closes when the user goes to another panel - the upload carries on
		upload.lightboxShown = () => !!(upload.doc && upload.doc.box && upload.doc.box.isConnected);

		upload.updateBar = (progress, info) => {
			if (!upload.lightboxShown()) {
				return;
			}
			if (upload.doc.footer.className === 'uploading') {
				upload.doc.upload.classList.add('loading');
			}
			upload.doc.barinfo1.textContent = info;
			upload.doc.barinfo2.textContent = info;
			upload.doc.bar.style.width = Math.round(progress * 100) + '%';
		};

		upload.updatePreviewImage = (callback) => {
			upload.doc.img.onload = () => {
				if (upload.fileInfo.type !== 'image/svg+xml') {
					upload.info.width = upload.doc.img.width;
					upload.info.height = upload.doc.img.height;
				}
				callback();
			};
			upload.resetPreviews();
			upload.doc.img.style.display = 'block';
			upload.doc.img.src = upload.src;
		};

		upload.updatePreviewPdf = (callback) => {
			callback();
			upload.resetPreviews();
			upload.doc.pdf.style.display = 'block';
			upload.doc.pdf.src = upload.src;
		};

		upload.updatePreviewVideo = (callback) => {
			upload.resetPreviews();
			upload.doc.video.src = upload.src;
			upload.doc.video.onloadedmetadata = () => {
				upload.info.width = upload.doc.video.videoWidth;
				upload.info.height = upload.doc.video.videoHeight;
				callback();
			};
		};

		upload.uploadFile = () => {
			upload.doc.box.className = 'uploading';
			upload.doc.footer.className = 'uploading';
			upload.size = upload.fileInfo.size;
			upload.spanIndex = 0;
			upload.spanCount = Math.max(1, Math.ceil(upload.size / setup.spanSize));
			// fixed at the start: the user may leave the lightbox for another panel meanwhile
			upload.from = location.pathname + location.search;
			upload.address = new URL('upload', location.href).pathname;
			upload.query = sys.http.parseURL(location.href).query;
			upload.fields = {
				name: upload.doc.name.value,
				label: upload.doc.label.value,
				type: upload.doc.type ? upload.doc.type.value : '',
				url: upload.doc.url ? upload.doc.url.value : '',
				info: upload.info
			};
			upload.uploadFileSpan();
		};

		upload.resetPreviews = () => {
			['pdf', 'img', 'video'].forEach((name) => {
				upload.doc[name].style.display = '';
				upload.doc[name].removeAttribute('src');
			});
			upload.info.width = null;
			upload.info.height = null;
		}

		upload.uploadFileSpan = () => {
			var spanSize, lastSpan;
			var spanStart = upload.spanIndex * setup.spanSize;

			if ((upload.spanIndex + 1) * setup.spanSize < upload.size) {
				spanSize = setup.spanSize;
				lastSpan = false; // maybe replce with spancount
			} else {
				spanSize = upload.size - upload.spanIndex * setup.spanSize;
				lastSpan = true;
			}
			var spanEnd = spanStart + spanSize;
			var reader = new FileReader();
			reader.onload = () => upload.sendFileSpan(reader.result, spanSize, lastSpan);
			reader.readAsArrayBuffer(upload.fileInfo.slice(spanStart, spanEnd));
		};

		upload.sendFileSpan = (binary, spanSize, lastSpan) => {
			var call = new sys.core.server.call(Object.assign({}, upload.fields, {
				size: upload.size,
				spanIndex: upload.spanIndex,
				spanSize: spanSize,
				lastSpan: lastSpan,
				// gone elsewhere: no redirect back to the page
				queue: lastSpan && location.pathname + location.search !== upload.from
			}));
			call.data.get = upload.query;
			call.binary = binary;
			call.send(upload.address)
					.onloadstart(e => {
						upload.updateBar(0, 'upload started');
					})
					.onupdate(e => {
						var barinfo = 'uploading filepart ' + (upload.spanIndex + 1) + ' / ' + upload.spanCount;
						var spanProgress = e.loaded / e.total;
						var progress = (upload.spanIndex + spanProgress) / upload.spanCount;
						upload.updateBar(progress, barinfo);
						upload.reportQueue(0, 1, null, progress);
					})
					.done(r => {
						l('done fired', r);
						if (lastSpan) {
							upload.reportQueue(1, 1);
							if (upload.lightboxShown()) {
								upload.doc.box.className = 'done';
								upload.doc.footer.className = 'done';
							}
						} else {
							upload.spanIndex++;
							upload.uploadFileSpan();
						}
					})
					.fail(() => upload.reportQueue(1, 1));

		};
	});
})();
