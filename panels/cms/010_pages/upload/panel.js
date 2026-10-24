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

		upload.readDroppedFile = () => {
			var reader = new FileReader();
			reader.onload = function () {
				upload.src = reader.result;
				upload.preview();
			};
			reader.onprogress = function (e) {
				upload.updateBar(e.loaded / e.total, 'reading file');
			};
			upload.doc.box.className = 'reading';
			upload.doc.footer.className = 'reading';
			reader.readAsDataURL(upload.fileInfo);
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
		upload.reportQueue = (done, total) => {
			if (!window.cmsJobs) {
				return;
			}
			if (done >= total) {
				cmsJobs.clear('upload');
				return;
			}
			cmsJobs.update('upload', { title: 'Uploading', done: done, total: total });
		};

		upload.queueDirect = (files, target, spinner) => {
			var list = Array.prototype.slice.call(files);
			if (!list.length || !spinner) {
				return;
			}
			spinner.classList.add('loading');
			upload.reportQueue(0, list.length);
			var step = (index) => {
				var last = index === list.length - 1;
				upload.sendFileDirect(list[index], target, list.length > 1, last).then(() => {
					upload.reportQueue(index + 1, list.length);
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

		upload.sendFileDirect = (file, target, queue, queueLast) => {
			return new Promise((resolve, reject) => {
				var reader = new FileReader();
				reader.onerror = () => reject(reader.error);
				reader.onload = () => {
					var blob = reader.result;
					var size = blob.byteLength;
					var spanSize = setup.spanSize;
					var spanCount = Math.max(1, Math.ceil(size / spanSize));
					var spanIndex = 0;
					var label = file.name.replace(/\.[^.]+$/, '');
					var sendSpan = () => {
						var start = spanIndex * spanSize;
						var last = spanIndex + 1 >= spanCount;
						var end = last ? size : start + spanSize;
						var call = new sys.core.server.call({
							name: file.name,
							label: label,
							info: {},
							size: size,
							spanIndex: spanIndex,
							spanSize: end - start,
							lastSpan: last,
							queue: !!queue,
							queueLast: !!(queue && last && queueLast)
						});
						call.data.get = {
							node_parent_node_id: target.node_parent_node_id,
							group: target.group || ''
						};
						if (target.node_id) {
							call.data.get.node_id = target.node_id;
						}
						call.data.method = 'put';
						call.binary = blob.slice(start, end);
						call.send('upload/upload').done(() => {
							if (!last) {
								spanIndex++;
								sendSpan();
								return;
							}
							if (queue && queueLast) {
								resolve();
								sys.core.server.sendGet(location.pathname + location.search);
								return;
							}
							resolve();
						});
					};
					sendSpan();
				};
				reader.readAsArrayBuffer(file);
			});
		};

		upload.bindFileDrop = () => {
			if (upload.fileDropBound) {
				return;
			}
			upload.fileDropBound = true;
			var depth = 0;
			var carriesFiles = (event) => {
				var types = event.dataTransfer && event.dataTransfer.types;
				return types && Array.prototype.indexOf.call(types, 'Files') !== -1;
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
				var files = Array.prototype.slice.call(event.dataTransfer.files || []);
				if (!files.length) {
					return;
				}
				var tile = event.target.closest('li[data-node_id]:not(.title):not(.upload)');
				var plus = event.target.closest('li.upload');
				var group = event.target.closest('li.title');
				var where = {tile: tile, plus: plus, group: group};
				var host = where.plus || (where.group && where.group.querySelector('li.upload'));
				if (files.length === 1) {
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
				var reader = new FileReader();
				reader.onload = function () {
					upload.blob = reader.result;
					upload.uploadFile();
				};
				reader.onprogress = function (e) {
					upload.updateBar(e.loaded / e.total, 'preparing file');
				};
				doc.box.className = 'preparing';
				reader.readAsArrayBuffer(upload.fileInfo);
			};
		};

		upload.readFileFromInput = (input) => {
			upload.fileInfo = input.files[0];
			var reader = new FileReader();
			reader.onload = function () {
				upload.src = reader.result;
				upload.preview();
			};
			reader.onprogress = function (e) {
				upload.updateBar(e.loaded / e.total, 'reading file');
			};
			upload.doc.box.className = 'reading';
			upload.doc.footer.className = 'reading';
			reader.readAsDataURL(upload.fileInfo);

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

		upload.updateBar = (progress, info) => {
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
			upload.size = upload.blob.byteLength;
			upload.spanIndex = 0;
			upload.spanCount = Math.ceil(upload.size / setup.spanSize);
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
			var call = new sys.core.server.call({
				name: upload.doc.name.value,
				label: upload.doc.label.value,
				type: upload.doc.type ? upload.doc.type.value : '',
				url: upload.doc.url ? upload.doc.url.value : '',
				info: upload.info,
				size: upload.size,
				spanIndex: upload.spanIndex,
				spanSize: spanSize,
				lastSpan: lastSpan
			});
			call.data.get = sys.http.parseURL(location.href).query;
			l(call.data.get);
			call.binary = upload.blob.slice(spanStart, spanEnd);
			call.send('upload')
					.onloadstart(e => {
						upload.updateBar(0, 'upload started');
					})
					.onupdate(e => {
						var barinfo = 'uploading filepart ' + (upload.spanIndex + 1) + ' / ' + upload.spanCount;
						var spanProgress = e.loaded / e.total;
						var progress = (upload.spanIndex + spanProgress) / upload.spanCount;
						upload.updateBar(progress, barinfo);
					})
					.done(r => {
						l('done fired', r);
						if (lastSpan) {
							upload.doc.box.className = 'done';
							upload.doc.footer.className = 'done';
						} else {
							upload.spanIndex++;
							upload.uploadFileSpan();
						}
					});

		};
	});
})();
