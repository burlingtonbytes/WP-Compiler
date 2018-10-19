<?php  if(!defined('ABSPATH')) { die(); } // Include in all php files, to prevent direct execution

function parse_path ($string) {
	$path_variables = WP_Compiler::get_path_vars();
	// prevent directory traversal
	$string = str_replace( '\\', '/', $string );
	$string = str_replace( '../', '/', $string );

	if ( $string ) {
		$has_var = false;
		foreach ( $path_variables as $var => $replace ) {
			$pattern = '/^{{' . preg_quote( $var ) . '}}/i';
			$parsed  = preg_replace( $pattern, $replace, $string );
			if ( $parsed != $string ) {
				$has_var = true;
				$string  = $parsed;
				break;
			}
		}
		if ( ! $has_var ) {
			$string = trailingslashit( WP_CONTENT_DIR ) . $string;
		}
	}

	return $string;
}
$options = get_option('wp_compiler_sources'); ?>
<div class="compiler-settings-wrapper">
	<h1><?php _e('WP Compiler Settings'); ?></h1>
	<p>
		By default, all paths start at wp-content, with a trailing slash.  To make configuration easier and more portable
		the following magic variables are available:
		<dl>
			<dt><code>{{theme}}</code></dt>
			<dd>the current theme directory</dd>
			<dd><?php echo trailingslashit(get_stylesheet_directory()); ?></dd>
			<dt><code>{{parent}}</code></dt>
			<dd>the parent theme directory</dd>
			<dd><?php echo trailingslashit(get_template_directory()); ?></dd>
			<dt><code>{{plugins}}</code></dt>
			<dd>the base plugins directory</dd>
			<dd><?php echo trailingslashit( WP_PLUGIN_DIR ); ?></dd>
			<dt><code>{{uploads}}</code></dt>
			<dd>the uploads directory</dd>
			<dd><?php echo trailingslashit( WP_Compiler::get_uploads_dir()); ?></dd>
		</dl>
	</p>
	<form action="options.php" method="post">
		<?php
		settings_fields( 'compiler-settings' );
		do_settings_sections( 'compiler-settings' );
		?>
		<div id="form_items">
			<?php if (!empty($options)) : ?>
				<?php foreach ($options as $key => $source) : ?>
					<div class="repeater">
						<div class="buttons">
							<a class="edit-source"><span class="dashicons dashicons-edit"></span> </a> <a class="remove-repeater" href="#"><span class="dashicons dashicons-trash"></span></a>
						</div>
						<div class="compiler-source-display">
							<h3 class="source-title"><?php echo $source['type']; ?></h3>
							<p><strong>Source:</strong>  <span class="source"><?php echo parse_path(esc_attr($source['source'])); ?></span></p>
							<p><strong>Target:</strong>  <span class="target"><?php echo parse_path(esc_attr($source['target'])); ?></span></p>
						</div>
						<div class="compiler-source-edit">
							<label>Source type
								<select class="source-type" name="wp_compiler_sources[<?php echo $key; ?>][type]" required>
									<option disabled>Choose file type</option>
									<option value="js" <?php selected( $source['type'], 'js' ); ?>>JavaScript (.manifest or directory)</option>
									<option value="scss" <?php selected( $source['type'], 'scss' ); ?>>SCSS</option>
									<option value="less" <?php selected( $source['type'], 'less'); ?>>LESS</option>
								</select>
							</label>
							<label>Source file
								<input type="text" class="new-source" name="wp_compiler_sources[<?php echo $key; ?>][source]" value="<?php echo esc_attr($source['source']); ?>" required>
							</label>
							<label>Target file
								<input type="text" class="new-target" name="wp_compiler_sources[<?php echo $key; ?>][target]" value="<?php echo esc_attr($source['target']); ?>" required>
							</label>
							<a class="edit-close button button-default">Done Editing</a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<p><a href="#" class="repeat button button-add-source" title="Add New Source"><span class="dashicons dashicons-plus"></span><span class="screen-reader-text">Add New Source</span></a></p>
		<?php submit_button('Save Changes'); ?>
	</form>
	<script type="html/template" class="input-template">
		<div class="repeater editing">
			<div class="buttons">
				<a class="edit-source"><span class="dashicons dashicons-edit"></span> </a> <a class="remove-repeater" href="#"><span class="dashicons dashicons-trash"></span></a>
			</div>
			<div class="compiler-source-display">
				<h3 class="source-title"></h3>
				<p><strong>Source:</strong>  <span class="source"></span></p>
				<p><strong>Target:</strong>  <span class="target"></span></p>
			</div>
			<div class="compiler-source-edit">
				<label>Source type
					<select  class="source-type" name="wp_compiler_sources[0][type]" required>
						<option disabled selected>Choose file type</option>
						<option value="js">JavaScript (.manifest or directory)</option>
						<option value="scss">SCSS</option>
						<option value="less">LESS</option>
					</select>
				</label>
				<label>Source file/folder
					<input type="text" class="new-source" name="wp_compiler_sources[0][source]" required>
				</label>
				<label>Target file
					<input type="text" class="new-target" name="wp_compiler_sources[0][target]" required>
				</label>
				<a class="edit-close button button-default">Done Editing</a>
			</div>
		</div>
	</script>
</div>
<?php
