(function ($) {
	'use strict';

	var $root = $('#bps-root');

	if (!$root.length) return;

	var $form = $('#form-module'),
		$save = $('#bps-save, #bps-save-foot'),
		$un = $('#bps-un, #bps-un-foot'),
		busy = false,
		baseline = '';

	function snapshot() { return JSON.stringify($form.serializeArray()); }

	function dirty() { return snapshot() !== baseline; }

	function sync() {
		var d = dirty();

		$un.prop('hidden', !d);
		$save.prop('disabled', !d || busy);
	}

	function toast(type, message) {
		var $box = $('#bps-toasts');

		if (!$box.length) $box = $('<div id="bps-toasts"></div>').appendTo('body');

		var $t = $('<div class="bps-toast' + (type === 'error' ? ' bps-toast-err' : '') + '"></div>')
			.append($('<i class="fa ' + (type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i>'))
			.append($('<span></span>').text(message))
			.appendTo($box);

		function close() { $t.removeClass('bps-toast-in'); setTimeout(function () { $t.remove(); }, 200); }

		setTimeout(function () { $t.addClass('bps-toast-in'); }, 10);
		setTimeout(close, 4000);
		$t.on('click', close);
	}

	function showTab(name) {
		var $tabs = $root.find('[data-bps-tab]'),
			$nav = $tabs.filter(function () { return $(this).data('bps-tab') === name; });

		if (!$nav.length) $nav = $tabs.first();

		name = $nav.data('bps-tab');

		$root.find('.bps-snav').removeClass('bps-on');
		$nav.addClass('bps-on');
		$root.find('.bps-pane').removeClass('bps-on');
		$('#bps-' + name).addClass('bps-on');
	}

	// The admin header sets <base href>, so a bare #hash would resolve to /admin/#hash and drop route and token.
	var pageUrl = location.href.split('#')[0];

	$root.find('[data-bps-tab]').each(function () { $(this).attr('href', pageUrl + '#bps-' + $(this).data('bps-tab')); });

	$root.on('click', '[data-bps-tab]', function (e) {
		e.preventDefault();

		var name = $(this).data('bps-tab');

		showTab(name);

		if (window.history && history.replaceState) history.replaceState(null, '', pageUrl + '#bps-' + name);
	});

	showTab(String(location.hash).replace('#bps-', ''));

	/* drag to reorder editor tabs */
	var list = document.getElementById('bps-tab-list'), dragEl = null;

	list.addEventListener('dragstart', function (e) {
		dragEl = e.target.closest('li');
		if (!dragEl) return;
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', '');
		dragEl.classList.add('bps-drag');
	});
	list.addEventListener('dragover', function (e) {
		if (!dragEl) return;
		e.preventDefault();

		var over = e.target.closest('li');

		if (!over || over === dragEl) return;

		var r = over.getBoundingClientRect();

		list.insertBefore(dragEl, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over);
	});
	list.addEventListener('dragend', function () {
		if (dragEl) dragEl.classList.remove('bps-drag');
		dragEl = null;
		sync();
	});

	/* change tracking */
	$root.on('input change', '[data-bps-field]', sync);

	baseline = snapshot();
	sync();

	$(window).on('beforeunload', function () { if (dirty()) return $root.data('unsaved'); });

	/* save */
	function save() {
		if (busy || !dirty()) return;

		busy = true;
		$save.prop('disabled', true).find('i').attr('class', 'fa fa-spinner fa-spin');

		$.ajax({
			url: $root.data('save'),
			type: 'post',
			data: $form.serialize(),
			dataType: 'json'
		}).done(function (json) {
			if (json.success) {
				baseline = snapshot();
				toast('success', json.success);
			} else {
				toast('error', (json.error && json.error.warning) || $root.data('denied'));
			}
		}).fail(function (xhr) {
			toast('error', (xhr && xhr.status ? xhr.status + ' ' + xhr.statusText + '. ' : '') + $root.data('fail'));
		}).always(function () {
			busy = false;
			$save.find('i').attr('class', 'fa fa-check');
			sync();
		});
	}

	$save.on('click', save);

	/* help tab: license and support come from the shared validate module */
	if ($root.data('support')) $('#support').load($root.data('support'));
	if ($root.data('license')) $('#license').load($root.data('license'));

	/* new version hint */
	if ($root.data('version-url')) {
		$.get($root.data('version-url'), function (data) {
			var current = String($root.data('version')).replace(/\D/g, ''), latest = String(data).replace(/\D/g, '');

			if (latest && Number(current) < Number(latest)) {
				$('<div class="bps-ver"><i class="fa fa-exclamation-circle"></i> </div>').append($root.data('upgrade')).insertBefore('.bps-ph');
			}
		});
	}

	$form.on('submit', function (e) { e.preventDefault(); save(); });

	$(document).on('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
			e.preventDefault();
			save();
		}
	});
})(jQuery);
