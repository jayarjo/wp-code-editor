(function($, undefined) {
	
	function $_GET(name) {
		var vars, pair;
		if (window.location.search) {
			vars = window.location.search.substring(1).split('&');
			for (var i = 0; i < vars.length; i++) {
				pair = vars[i].split('=');
				if (decodeURIComponent(pair[0]) == name) {
					return decodeURIComponent(pair[1]);
				}
			}
		}
		return undefined;
	}


	function initACE(id, mode) {
		var $textArea = $('#'+id), ed, sess;
		var ext2mode = {
			js: 'javascript'
		};

		ace.require("ace/ext/language_tools");
		ed = ace.edit('wce-'+id);
		ed.setTheme("ace/theme/monokai");
		ed.setOptions({
		    enableBasicAutocompletion: true
		});

		sess = ed.getSession();
		sess.setMode("ace/mode/" + (ext2mode[mode] || mode || "html"));
		sess.setUseWrapMode(true);
		sess.setValue($textArea.val());
		sess.on('change', function() {
			$textArea.val(sess.getValue());
		});

		$('#wce-'+id).data('ace', ed);
	}


	function initJSTree(data) {
		var $container = $('#templateside')
		, $ul
		, $tree
		, ext
		;

		if (!$container.length) {
			return;
		}

		$ul = $container.find('ul').hide();
		$tree = $('<div class="wce-tree" />');

		var isPlugin = !!(ext = $('#template [name="plugin"]').val());
		if (!ext) {
			ext = $('#template [name="theme"]').val();
		}


		$.post(ajaxurl, {
			action: 'wce_get_files',
			file: $('#template [name="file"]').val(),
			type: isPlugin ? 'plugin' : 'theme',
			ext: ext
		}, function(data) {
			console.info(data);
			$ul.after($tree);

			$tree
				.before('<h3 class="wce-h3">Files</h3>')
				.on('activate_node.jstree', function(e, data) {
					if (data.node.icon == 'jstree-file') {
						window.location = data.node.a_attr.href;
					}
				})
				.jstree({
					core: {
						data: data
					}
				});
		}, 'json');
	}


	var WCE = {
		show: function(id, mode) {
			var $ed = $('#wce-'+id), $textArea = $('#'+id), h, w;
			if (!$ed.data('ace')) {
				initACE(id, mode);
			}

			w = parseInt($textArea.css('width') || $textArea.width(), 10);
			h = parseInt($textArea.css('height') || $textArea.height(), 10);

			$ed.css({
				width: w+'px',
				height: h+'px'
			}).show();

			$ed.data('ace').resize(true);
			$textArea.hide();
		},

		hide: function(id) {
			$('#'+id).show();
			$('#wce-'+id).hide();
		}
	};


	$('.wp-editor-area').each(function() {
		var $this = $(this), id = $this.attr('id');

		$this.after('<div id="wce-'+id+'" />');
		
		$('#'+id+'-html').attr('onclick', "switchEditors.switchto(this);WCE.show('"+id+"');");
		$('#'+id+'-tmce').attr('onclick', "WCE.hide('"+id+"');switchEditors.switchto(this);");

		if ($('#wp-'+id+'-wrap').hasClass('html-active')) {
			WCE.show(id);
		}
	});

	$('#newcontent').each(function() {
		var $this = $(this), id = $this.attr('id'), file;
		// there's explicit css rule (#tmaplate div) that breaks the editor
		$this.closest('#template').before('<div id="wce-'+id+'" />');

		// load corresponding mode depending on the requested file type
		file = $('[name="file"]').val();
		if (file) {
			var m = file.match(/\.(\w+)$/);
			if (m) {
				WCE.show(id, m[1]);
				return true;
			}
		}

		WCE.show(id);
	});

	initJSTree();
	
	window.WCE = WCE;
}(jQuery));