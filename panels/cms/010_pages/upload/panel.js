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

		upload.open = (event) => {
			upload.event = event;
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

			var info = '<b>Original Format</b>';
			if (upload.info.width) {
				info += '<br>' + upload.info.width + ' x ' + upload.info.height + 'px';
				info += '<br>(' + printFileSize(upload.fileInfo.size) + ')';
			} else {
				info += '<br>' + printFileSize(upload.fileInfo.size) + '';
			}

			info += '<br><br><b>Mime Type</b><br>' + upload.fileInfo.type;

			upload.doc.info.innerHTML = info;
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
			upload.doc.video.style.display = 'block';
			upload.doc.video.src = upload.src;
			upload.doc.video.onloadedmetadata = event => {
				window.URL.revokeObjectURL(upload.doc.video.src);
				const {videoWidth, videoHeight} = upload.doc.video;
				upload.info.width = videoWidth;
				upload.info.height = videoHeight;
				callback();
			}
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
			upload.doc.pdf.style.display = 'none';
			upload.doc.pdf.src = '';
			upload.doc.img.style.display = 'none';
			upload.doc.img.src = '';
			upload.doc.video.style.display = 'none';
			upload.doc.video.src = '';
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
