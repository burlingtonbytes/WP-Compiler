(jQuery)(function($) {
	var attrs = ['for', 'id', 'name'];
	var $form = $('#form_items');
	bindEvents();

	function resetAttributeNames() {
		var sections = $('.repeater');
		sections.each(function(){
			var $this = $(this),
				tags = $this.find('input, label, select'),
				idx = $this.index();
			tags.each(function() {
				var $this = $(this);
				$.each(attrs, function(i, attr) {
					var attr_val = $this.attr(attr);
					if (attr_val) {
						$this.attr(attr, attr_val.replace(/wp_compiler_sources\[\d+]\[/, 'wp_compiler_sources\['+(idx)+'\]\['));
					}
				})
			})
		});
	}
	function bindEvents() {
		$form.on('click', '.remove-repeater', function(e) {
			e.preventDefault();
			if (window.confirm("Are you sure you want to remove this source?")) {
				$(this).closest('.repeater').remove();
				if( !$form.children().length ) {
					addSource();
				}
				resetAttributeNames()
			}
		})
	}
	function addSource() {
		var template = $('.input-template').html();
		$form.append(template);
		resetAttributeNames()
	}
	$('.repeat').click(function(e){
		e.preventDefault();
		addSource();
	});
	$form.on('click', '.edit-source', function (e) {
		e.preventDefault();
		var $container = $(this).closest('.repeater');
		if ($container.length) {
			$container.addClass('editing');
		}
	});
	$form.on('click', '.edit-close', function (e) {
		e.preventDefault();
		var $container = $(this).closest('.repeater');
		if ($container.length && $container.hasClass('editing')) {
			var pass = validate_source($container);
			if (!pass) {
				e.preventDefault();
			} else {
				$container.removeClass('editing');
				var $source = $container.find('.new-source'),
					$target = $container.find('.new-target'),
					$type   = $container.find('.source-type'),
					$keys   = Object.getOwnPropertyNames(wp_compiler_paths);
				if ($source.length) {
					var new_source = $source.val();
					for (var i=0;i<$keys.length;i++) {
						console.log($keys[i]);
						new_source = new_source.replace('{{' + $keys[i] + '}}', wp_compiler_paths[$keys[i]]);
					}
					if (typeof new_source !== 'undefined') {
						var $old_source = $container.find('.source');
						if ($old_source.length) {
							$old_source.text(new_source);
						}
					}
				}
				if ($target.length) {
					var new_target = $target.val();
					for (var j=0;j<$keys.length;j++) {
						new_target = new_target.replace('{{' + $keys[j] + '}}', wp_compiler_paths[$keys[j]]);
					}
					if (typeof new_target !== 'undefined') {
						var $old_target = $container.find('.target');
						if ($old_target.length) {
							$old_target.text(new_target);
						}
					}
				}
				if ($type.length) {
					var new_type = $type.val();
					if (typeof new_type !== 'undefined') {
						var $old_type = $container.find('.source-title');
						if ($old_type.length) {
							$old_type.text(new_type);
						}
					}
				}
			}
		}
	});
	if ($('.repeater').length === 0) {
		addSource();
	}
	$('#submit').on('click', function (e) {
		var $sources = $('.repeater');
		var pass = validate_source($sources);
		if (!pass) {
			e.preventDefault();
		}
	});
	function validate_source ($sources) {
		var flag = false;
		$sources.each(function () {
			$sources.css('border', 'none');
			var $this = $(this),
				$source = $this.find('.new-source'),
				$target = $this.find('.new-target'),
				$type = $this.find('.source-type'),
				target_type = null,
				source_val = $source.val(),
				target_val = $target.val(),
				type_val = $type.val(),
				inner_flag = false;
			$source.css('border', 'none');
			$target.css('border', 'none');
			$type.css('border', 'none');

			if( !type_val && !source_val && !target_val ) {
				return;
			}

			if (type_val) {
				if (type_val === 'js') {
					target_type = 'js';
				} else {
					target_type = 'css';
				}
			}
			if ( !type_val ) {
				$type.css('border', '2px solid red');
				inner_flag = true;
			}
			if ( !source_val ) {
				$source.css('border', '2px solid red');
				inner_flag = true;
			}
			if ( !target_val || target_val.substr(( target_val.length - target_type.length )) !== target_type ) {
				$target.css('border', '2px solid red');
				inner_flag = true;
			}
			if ( inner_flag === true ) {
				$this.css('border', '2px solid red');
				flag = true;
			}
		});
		if (!flag) {
			return true;
		}
		setTimeout(function() {
			window.alert("Please resolve errors in highlighted sections");
		}, 50)
	}
});
