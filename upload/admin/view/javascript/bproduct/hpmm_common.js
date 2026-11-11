/* global $ */
if (typeof window.getURLVar !== 'function') {
	window.getURLVar = function(key) {
		var m = String(document.location).match(new RegExp('[?&]' + key + '=([^&#]*)'));

		return m ? m[1] : '';
	};
}

// Image field popover: upload by drag and drop, file dialog, URL download or open the file manager.
// Used by the Quick Product Manager only when the hpmm module does not provide hpmmUploader.
var bpUploader = (function() {
	var ICON_UPLOAD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 16V5m0 0-4 4m4-4 4 4M4 15v4h16v-4"/></svg>';
	var ICON_SELECT = '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 1 3.5 5.5h3V10h3V5.5h3L8 1ZM2 12v2.5h12V12h-1.5v1H3.5v-1H2Z"/></svg>';
	var ICON_FOLDER = '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M1 3h5l1.5 1.5H15V13H1V3Z"/></svg>';
	var ICON_LINK   = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6.5 9.5a2.5 2.5 0 0 0 3.5 0l2.5-2.5a2.5 2.5 0 0 0-3.5-3.5L8 4.5M9.5 6.5a2.5 2.5 0 0 0-3.5 0L3.5 9a2.5 2.5 0 0 0 3.5 3.5L8 11.5"/></svg>';
	var ICON_TRASH  = '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M5.5 1h5l.5 1.5H14V4H2V2.5h3L5.5 1ZM3 5h10l-.7 9.5H3.7L3 5Z"/></svg>';
	var TYPES = /^image\/(jpeg|pjpeg|png|x-png|gif|webp)$/;
	var limits = null;
	var $pop = null;
	var $anchor = null;
	var opts = {};
	var styled = false;

	var TEXT = {
		select: 'Select file',
		manager: 'Manager',
		url: 'URL',
		clear: 'Clear',
		download: 'Download',
		drag: 'Drag an image here',
		dragMany: 'Drag images here',
		drop: 'Drop to upload',
		failed: 'Upload failed',
		retry: 'Try again',
		downloading: 'Downloading...',
		urlPlaceholder: 'https://example.com/photo.jpg',
		types: 'JPG, PNG, GIF, WEBP',
		badType: 'Warning: Only JPG, PNG, GIF and WEBP images are allowed!',
		tooBig: 'Warning: The file is larger than the allowed size!',
		generic: 'Warning: The upload failed, please try again!'
	};

	function injectStyle() {
		if (styled) {
			return;
		}

		styled = true;

		$('head').append('<style id="bpup-style">' +
			'.hpmm-pop{position:absolute;z-index:1080;box-sizing:border-box;background:#fff;border:1px solid #d9dde1;border-radius:6px;box-shadow:0 6px 22px rgba(20,30,40,.16);padding:14px;font-size:14px;line-height:1.45;color:#2b3036}' +
			'.hpmm-pop *{box-sizing:border-box}' +
			'.hpmm-pop.right:before{content:"";position:absolute;left:-7px;top:36px;width:12px;height:12px;background:#fff;border-left:1px solid #d9dde1;border-bottom:1px solid #d9dde1;transform:rotate(45deg)}' +
			'.hpmm-pop.below:before{content:"";position:absolute;top:-7px;left:34px;width:12px;height:12px;background:#fff;border-left:1px solid #d9dde1;border-top:1px solid #d9dde1;transform:rotate(45deg)}' +
			'.hpmm-btns{display:flex;flex-wrap:wrap;margin-bottom:10px}' +
			'.hpmm-btn{display:inline-flex;align-items:center;gap:7px;font:inherit;line-height:1.2;padding:8px 14px;border:1px solid #d9dde1;background:#fff;color:#2b3036;cursor:pointer;border-radius:0;margin-left:-1px}' +
			'.hpmm-btn:first-child{margin-left:0;border-radius:3px 0 0 3px}' +
			'.hpmm-btn:last-child{border-radius:0 3px 3px 0}' +
			'.hpmm-btn svg{width:15px;height:15px;flex:none}' +
			'.hpmm-btn.on{background:#e8f1fa;color:#2f7bbf}' +
			'.hpmm-btn.green{background:#4aa84a;border-color:#4aa84a;color:#fff}' +
			'.hpmm-btn.red{background:#cf4a35;border-color:#cf4a35;color:#fff}' +
			'.hpmm-btn.blue{background:#2f7bbf;border-color:#2f7bbf;color:#fff}' +
			'.hpmm-btn:focus,.hpmm-drop:focus,.hpmm-pop input[type=text]:focus{outline:2px solid #2f7bbf;outline-offset:2px}' +
			'.hpmm-drop{display:flex;align-items:center;justify-content:center;gap:10px;min-height:110px;padding:24px 12px;border:2px dashed #d9dde1;border-radius:4px;background:#f8fafb;color:#6b737c;text-align:center;cursor:pointer;transition:border-color .12s,background .12s}' +
			'.hpmm-drop svg{width:22px;height:22px;flex:none;opacity:.8}' +
			'.hpmm-drop strong{color:#2b3036;font-weight:500}' +
			'.hpmm-drop.over{border-color:#2f7bbf;background:#e8f1fa;color:#2f7bbf}' +
			'.hpmm-drop.over strong{color:#2f7bbf}' +
			'.hpmm-drop.busy{display:block;border-style:solid;cursor:default;text-align:left}' +
			'.hpmm-drop.fail{border-color:#cf4a35;background:#fdecea;color:#a63222}' +
			'.hpmm-drop.fail strong{color:#a63222}' +
			'.hpmm-file{display:flex;gap:10px;align-items:center}' +
			'.hpmm-file span:first-child{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:ui-monospace,Menlo,monospace;font-size:12px;color:#2b3036}' +
			'.hpmm-file span:last-child{font-size:12px;color:#6b737c}' +
			'.hpmm-bar{height:6px;margin-top:8px;border-radius:3px;background:#d9dde1;overflow:hidden}' +
			'.hpmm-bar i{display:block;height:100%;width:0;background:#4aa84a;transition:width .15s linear}' +
			'.hpmm-bar.wait i{width:40%;animation:hpmm-wait 1s ease-in-out infinite alternate}' +
			'@keyframes hpmm-wait{from{margin-left:0}to{margin-left:60%}}' +
			'.hpmm-retry{font:inherit;background:none;border:0;padding:0;color:#2f7bbf;text-decoration:underline;cursor:pointer}' +
			'.hpmm-url{display:flex;margin-top:10px}' +
			'.hpmm-url input{flex:1;min-width:0;font:inherit;padding:8px 12px;border:1px solid #d9dde1;border-right:0;border-radius:3px 0 0 3px;background:#fff;color:#2b3036;height:auto;box-shadow:none}' +
			'.hpmm-url .hpmm-btn{border-radius:0 3px 3px 0;margin-left:0}' +
			'.hpmm-hint{margin:10px 0 0;font-size:13px;color:#6b737c}' +
			'@media (max-width:520px){.hpmm-lbl{display:none}.hpmm-btns .hpmm-btn{flex:1;justify-content:center;padding:10px 14px}.hpmm-url .hpmm-btn{padding:10px 14px}}' +
			'</style>');
	}

	function closePop() {
		if ($pop) {
			$pop.remove();
			$pop = null;
		}

		$(document).off('.hpmmpop');
		$(window).off('.hpmmpop');
		$anchor = null;
	}

	function place() {
		if (!$pop || !$anchor) {
			return;
		}

		var rect = $anchor[0].getBoundingClientRect();
		var vw = $(window).width();
		var sx = $(window).scrollLeft();
		var sy = $(window).scrollTop();
		var width = Math.min(440, vw - 24);
		var left = rect.right + 12;

		if (vw - left - 12 >= 340) {
			width = Math.min(440, vw - left - 12);
			$pop.removeClass('below').addClass('right').css({
				width: width,
				left: left + sx,
				top: rect.top + sy + rect.height / 2 - 42
			});
		} else {
			$pop.removeClass('right').addClass('below').css({
				width: width,
				left: Math.max(12, Math.min(rect.left, vw - width - 12)) + sx,
				top: rect.bottom + sy + 12
			});
		}
	}

	function loadLimits(done) {
		if (limits) {
			done(limits);

			return;
		}

		$.getJSON(window.BP_MEDIA_BASE + 'limits', function(json) {
			limits = json;
			done(json);
		}).fail(function() {
			done(null);
		});
	}

	function dropHtml(state, name, pct) {
		if (state === 'busy') {
			return '<div class="hpmm-file"><span></span><span>' + (pct === null ? '' : pct + '%') + '</span></div><div class="hpmm-bar' + (pct === null ? ' wait' : '') + '"><i style="width:' + (pct === null ? 40 : pct) + '%"></i></div>';
		}

		if (state === 'fail') {
			return ICON_UPLOAD + '<div><strong>' + TEXT.failed + '</strong> <span class="hpmm-msg"></span> <button type="button" class="hpmm-retry">' + TEXT.retry + '</button></div>';
		}

		if (state === 'over') {
			return ICON_UPLOAD + '<div><strong>' + TEXT.drop + '</strong></div>';
		}

		return ICON_UPLOAD + '<div><strong>' + (opts.multiple ? TEXT.dragMany : TEXT.drag) + '</strong></div>';
	}

	function setDrop(state, name, pct, message) {
		var $drop = $pop.find('.hpmm-drop');

		$drop.attr('class', 'hpmm-drop' + (state === 'over' ? ' over' : '') + (state === 'busy' ? ' busy' : '') + (state === 'fail' ? ' fail' : ''));
		$drop.html(dropHtml(state, name, pct));
		$drop.find('.hpmm-file span:first-child').text(name || '');
		$drop.find('.hpmm-msg').text(message || '');
	}

	function query($element) {
		var language_id = typeof $element.data('language') !== 'undefined' ? $element.data('language') : 0;
		var product_id = typeof $element.data('productid') !== 'undefined' ? $element.data('productid') : 0;
		var product_seo = $('#slug-result' + language_id).length ? $('#slug-result' + language_id).val() : '';
		var directory = localStorage.getItem('lastFolder') ? '&directory=' + encodeURIComponent(localStorage.getItem('lastFolder')) : '';

		return {
			product_id: product_id,
			product_seo: product_seo,
			directory: directory,
			target: $element.parent().find('input').attr('id'),
			thumb: $element.attr('id')
		};
	}

	function applyFile($element, file) {
		$element.find('img').attr('src', file.thumb);
		$element.parent().find('input').val(file.path);
		closePop();
	}

	function handleResponse($element, json) {
		if (json && json.files && json.files.length && opts.onFiles) {
			var done = opts.onFiles;

			closePop();
			done(json.files);
		} else if (json && json.files && json.files.length) {
			applyFile($element, json.files[0]);
		} else {
			setDrop('fail', '', 0, (json && json.error) ? json.error : TEXT.generic);
		}
	}

	function uploadFile($element, files) {
		var q = query($element);
		var data = new FormData();
		var i;

		for (i = 0; i < files.length; i++) {
			if (!TYPES.test(files[i].type)) {
				setDrop('fail', '', 0, TEXT.badType);

				return;
			}

			if (limits && limits.max_size && files[i].size > limits.max_size) {
				setDrop('fail', '', 0, TEXT.tooBig);

				return;
			}

			data.append('file[]', files[i]);
		}

		setDrop('busy', files.length > 1 ? files.length + ' images' : files[0].name, 0);

		$.ajax({
			url: window.BP_MEDIA_BASE + 'upload' + q.directory,
			type: 'post',
			data: data,
			dataType: 'json',
			cache: false,
			contentType: false,
			processData: false,
			xhr: function() {
				var xhr = $.ajaxSettings.xhr();

				if (xhr.upload) {
					xhr.upload.addEventListener('progress', function(e) {
						if (e.lengthComputable && $pop) {
							var pct = Math.round(e.loaded / e.total * 100);

							$pop.find('.hpmm-file span:last-child').text(pct + '%');
							$pop.find('.hpmm-bar i').css('width', pct + '%');
						}
					});
				}

				return xhr;
			},
			success: function(json) {
				if ($pop) {
					handleResponse($element, json);
				}
			},
			error: function() {
				if ($pop) {
					setDrop('fail', '', 0, TEXT.generic);
				}
			}
		});
	}

	function downloadUrl($element, url) {
		var q = query($element);

		if (!$.trim(url)) {
			$pop.find('.hpmm-url input').focus();

			return;
		}

		setDrop('busy', url, null);

		$.ajax({
			url: window.BP_MEDIA_BASE + 'download' + q.directory,
			type: 'post',
			data: {url: $.trim(url)},
			dataType: 'json',
			success: function(json) {
				if ($pop) {
					handleResponse($element, json);
				}
			},
			error: function() {
				if ($pop) {
					setDrop('fail', '', 0, TEXT.generic);
				}
			}
		});
	}

	function openManager($element, $button) {
		var q = query($element);
		var $icon = $button.find('> i');

		$('#modal-image').remove();

		$.ajax({
			url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token') + '&target=' + q.target + '&thumb=' + q.thumb + q.directory,
			dataType: 'html',
			success: function(html) {
				$('body').append('<div id="modal-image" class="modal">' + html + '</div>');

				$('#modal-image').modal('show');
			}
		});

		closePop();
	}

	function lbl(text) {
		return '<span class="hpmm-lbl">' + text + '</span>';
	}

	function open($element) {
		injectStyle();
		closePop();

		$anchor = $element;

		$pop = $(
			'<div class="hpmm-pop" role="dialog">' +
				'<div class="hpmm-btns">' +
					'<button type="button" class="hpmm-btn green" data-act="select" title="' + TEXT.select + '" aria-label="' + TEXT.select + '">' + ICON_SELECT + lbl(TEXT.select) + '</button>' +
					'<button type="button" class="hpmm-btn" data-act="manager" title="' + TEXT.manager + '" aria-label="' + TEXT.manager + '">' + ICON_FOLDER + lbl(TEXT.manager) + '</button>' +
					'<button type="button" class="hpmm-btn" data-act="url" aria-expanded="false" title="' + TEXT.url + '" aria-label="' + TEXT.url + '">' + ICON_LINK + lbl(TEXT.url) + '</button>' +
					'<button type="button" class="hpmm-btn red" data-act="clear" title="' + TEXT.clear + '" aria-label="' + TEXT.clear + '">' + ICON_TRASH + lbl(TEXT.clear) + '</button>' +
				'</div>' +
				'<div class="hpmm-drop" tabindex="0" role="button" aria-label="' + TEXT.drag + '"></div>' +
				'<div class="hpmm-url" style="display:none">' +
					'<input type="text" inputmode="url" placeholder="' + TEXT.urlPlaceholder + '" aria-label="' + TEXT.urlPlaceholder + '">' +
					'<button type="button" class="hpmm-btn blue" data-act="download" title="' + TEXT.download + '" aria-label="' + TEXT.download + '">' + ICON_LINK + lbl(TEXT.download) + '</button>' +
				'</div>' +
				'<p class="hpmm-hint">' + TEXT.types + '</p>' +
				'<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">' +
			'</div>'
		).appendTo('body');

		setDrop('idle');
		place();

		loadLimits(function(json) {
			if (json && $pop) {
				$pop.find('.hpmm-hint').text(TEXT.types + ', up to ' + json.max_text);
			}
		});

		var $file = $pop.find('input[type=file]');

		$file.prop('multiple', !!opts.multiple);

		if (opts.noClear) {
			$pop.find('[data-act="clear"]').remove();
		}
		var $drop = $pop.find('.hpmm-drop');

		$file.on('change', function() {
			if (this.files.length) {
				uploadFile($element, opts.multiple ? this.files : [this.files[0]]);
			}

			this.value = '';
		});

		function pick() {
			if (!$drop.hasClass('busy')) {
				$file.trigger('click');
			}
		}

		$drop.on('click', function(e) {
			if ($(e.target).hasClass('hpmm-retry')) {
				setDrop('idle');

				return;
			}

			pick();
		}).on('keydown', function(e) {
			if (e.which === 13 || e.which === 32) {
				e.preventDefault();
				pick();
			}
		}).on('dragover dragenter', function(e) {
			e.preventDefault();

			if (!$drop.hasClass('busy') && !$drop.hasClass('over')) {
				setDrop('over');
			}
		}).on('dragleave', function(e) {
			e.preventDefault();

			if ($drop.hasClass('over')) {
				setDrop('idle');
			}
		}).on('drop', function(e) {
			e.preventDefault();

			var files = e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : [];

			if ($drop.hasClass('busy')) {
				return;
			}

			if (files.length) {
				uploadFile($element, opts.multiple ? files : [files[0]]);
			} else {
				setDrop('idle');
			}
		});

		var $url = $pop.find('.hpmm-url');

		$pop.on('click', '.hpmm-btn', function() {
			var $button = $(this);

			switch ($button.data('act')) {
				case 'select':
					pick();
					break;
				case 'manager':
					openManager($element, $button);
					break;
				case 'url':
					var show = !$url.is(':visible');

					$url.toggle(show);
					$button.toggleClass('on', show).attr('aria-expanded', show ? 'true' : 'false');
					place();

					if (show) {
						$url.find('input').focus();
					}
					break;
				case 'clear':
					$element.find('img').attr('src', $element.find('img').attr('data-placeholder'));
					$element.parent().find('input').val('');
					closePop();
					break;
				case 'download':
					downloadUrl($element, $url.find('input').val());
					break;
			}
		});

		$url.find('input').on('keydown', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				downloadUrl($element, $(this).val());
			}
		});

		// Close on outside click or Escape, keep position on resize
		setTimeout(function() {
			$(document).on('mousedown.hpmmpop', function(e) {
				if ($pop && !$(e.target).closest('.hpmm-pop, #modal-image').length && !$(e.target).closest($anchor).length) {
					closePop();
				}
			}).on('keydown.hpmmpop', function(e) {
				if (e.which === 27) {
					closePop();
				}
			});

			$(window).on('resize.hpmmpop', place);
		}, 0);
	}

	return {
		toggle: function($element, options) {
			opts = options || {};

			if ($anchor && $anchor[0] === $element[0]) {
				closePop();
			} else {
				open($element);
			}
		}
	};
})();
