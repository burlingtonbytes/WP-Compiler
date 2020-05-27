<?php
/*
	Plugin Name: WP Compiler
	Plugin URI: https://github.com/burlingtonbytes/WP-Compiler
	Description: Compiles and minifies SCSS, LESS and JS
	Author: Bytes Co
	Author URI: https://bytes.co
	Version: 1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	die();
} // Include in all php files, to prevent direct execution

require_once 'vendor/autoload.php';

use ScssPhp\ScssPhp\Compiler;
use MatthiasMullie\Minify\Minify as Minify;
if ( ! class_exists( 'WP_Compiler' ) ) {
	class WP_Compiler {
		public static $version = 'v1.0.0';
		private static $_this;
		private $plugin_dir;
		private $plugin_dir_url;
		private $options;
		private $now;
		private $errors;

		public static function Instance() {
			static $instance = null;
			if ( $instance === null ) {
				$instance = new self();
			}

			return $instance;
		}

		protected function __construct() {
			$this->plugin_dir     = dirname( __FILE__ );
			$this->plugin_dir_url = plugin_dir_url( __FILE__ );
			$this->options        = $this->get_options();
			$this->now            = date( 'Y-m-d g:i:s e' );
			add_action( 'init', array( $this, 'watch_src_folders' ) );
			add_action( 'admin_bar_menu', array( $this, 'add_compiler_button' ), 999 );
			add_action( 'admin_post_wp_compiler_compile', array( $this, 'process_compile_request' ) );
			add_action( 'admin_post_wp_compiler_compile_dev_mode', array( $this, 'process_dev_mode_request' ) );
			add_action( 'admin_head', array( $this, 'adminbar_style' ) );
			add_action( 'wp_head', array( $this, 'adminbar_style' ) );
			add_filter( 'wp_compiler_dev_mode', array( $this, 'announce_dev_mode' ) );
			add_filter( 'wp_compiler_script_version', array( $this, 'compiler_script_version' ), 10, 2 );
			add_filter( 'wp_compiler_style_version', array( $this, 'compiler_style_version' ), 10, 2 );
			add_filter( 'bbytes_dev_utils_dev_mode', array( $this, 'announce_dev_mode' ) );
			add_filter( 'bbytes_compiler_script_version', array( $this, 'compiler_script_version' ), 10, 2 );
			add_filter( 'bbytes_compiler_style_version', array( $this, 'compiler_style_version' ), 10, 2 );
			add_action( 'admin_menu', array( $this, 'make_options_page' ) );
			add_action( 'admin_init', array( $this, 'register_compiler_settings' ) );
			add_action( 'admin_footer', array( $this, 'enqueue_compiler_admin_scripts' ) );
			add_action( 'admin_notices', array( $this, 'show_errors' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
		}

		public function show_errors() {
			$options = $this->get_options();
			if ( ! empty( $options['error_messages'] ) && is_array( $options['error_messages'] ) ) {
				foreach ( $options['error_messages'] as $error ) {
					?>
					<div class="notice notice-error">
						<h3>WP Compiler Error</h3>
						<p>
							<strong><?php echo $error['type']; ?> error:</strong>
							<?php echo $error['message']; ?>
						</p>
					</div>
					<?php
				}
			}
		}

		public function register_compiler_settings() {
			register_setting( 'compiler-settings', 'wp_compiler_sources' );
		}

		public function enqueue_compiler_admin_scripts() {
			$path_variables = self::get_path_vars();
			wp_register_script( 'compiler-settings', $this->plugin_dir_url . '/includes/js/settingsPage.js' );
			wp_enqueue_style( 'compiler-admin-styles', $this->plugin_dir_url . 'includes/css/admin-styles.css' );
			wp_localize_script( 'compiler-settings', 'wp_compiler_paths', $path_variables );
			wp_enqueue_script( 'compiler-settings' );
		}

		public function make_options_page() {
			add_options_page( 'WP Compiler Settings', 'WP Compiler', 'manage_options', 'wp-compiler', array( $this, 'populate_options_page' ) );
		}

		public function populate_options_page() {
			include $this->plugin_dir . '/includes/templates/option-page.php';
		}

		public function add_settings_link( $links ) {
			$links[] = '<a href="' . esc_url( get_admin_url( null, 'options-general.php?page=wp-compiler' ) ) . '">Settings</a>';

			return $links;
		}

		public function compiler_script_version( $version, $folder ) {
			$dev_mode = $this->announce_dev_mode();
			if ( isset( $this->options['output_hashes'][ $folder ]['js'] ) ) {
				$version = $this->options['output_hashes'][ $folder ]['js'];
				if ( $dev_mode ) {
					$version .= '-dev';
				}
			}

			return $version;
		}

		public function compiler_style_version( $version, $folder ) {
			$dev_mode = $this->announce_dev_mode();
			if ( isset( $this->options['output_hashes'][ $folder ]['css'] ) ) {
				$version = $this->options['output_hashes'][ $folder ]['css'];
				if ( $dev_mode ) {
					$version .= '-dev';
				}
			}

			return $version;
		}

		public function add_compiler_button( $wp_admin_bar ) {
			$dev_mode = $this->options['dev_mode'];
			$args     = array(
				'id'   => 'wp_compiler_compile',
				'href' => admin_url( 'admin-post.php' ) . '?action=wp_compiler_compile',
			);
			if ( ! $dev_mode ) {
				$args['title'] = __( 'Compile', 'wp_compiler' );
				$args['meta']  = array( 'class' => 'wp_compiler_compile' );
			} else {
				$args['title'] = __( 'Compile [DEV MODE ON]', 'wp_compiler' );
				$args['meta']  = array( 'class' => 'wp_compiler_compile wp_compiler_dev_mode_on' );
			}
			$wp_admin_bar->add_menu( $args );
			$args = array(
				'parent' => 'wp_compiler_compile',
				'id'     => 'wp_compiler_compile_toggler',
				'href'   => admin_url( 'admin-post.php' ) . '?action=wp_compiler_compile_dev_mode',
				'meta'   => array( 'class' => 'wp_compiler_compile_dev_mode' ),
			);
			if ( ! $dev_mode ) {
				$args['title'] = __( 'Enable Dev Mode', 'wp_compiler' );
			} else {
				$args['title'] = __( 'Disable Dev Mode', 'wp_compiler' );
			}
			$wp_admin_bar->add_menu( $args );
		}

		public function watch_src_folders() {
			$dev_mode = (bool) $this->options['dev_mode'];
			if ( $dev_mode ) {
				$this->compile_resources();
			}
		}

		public function process_compile_request() {
			$dev_mode = (bool) $this->options['dev_mode'];
			$user_id  = get_current_user_id();
			if ( $user_id && user_can( $user_id, 'switch_themes' ) ) {
				// force compilation, if manually requested
				$this->compile_resources( true );
				$this->redirect_to_referrer();
			}
		}

		public function process_dev_mode_request() {
			$dev_mode = (bool) $this->options['dev_mode'];
			$user_id  = get_current_user_id();
			if ( $user_id && user_can( $user_id, 'switch_themes' ) ) {
				if ( $dev_mode ) {
					$files = $this->get_sourcemap_filenames();
					foreach ( $files as $file ) {
						if ( file_exists( $file ) ) {
							unlink( $file );
						}
					}
				}
				// switch dev mode on or off
				$this->set_option( 'dev_mode', ! (bool) $this->options['dev_mode'] );
				// force compilation
				$this->compile_resources( true );
				$this->redirect_to_referrer();
			}
		}

		public function adminbar_style() {
			if ( get_current_user_id() ) {
				?>
				<style>
					#wpadminbar #wp-admin-bar-wp_compiler_compile > .ab-item:before {
						content: "\f316";
					}

					#wpadminbar #wp-admin-bar-wp_compiler_compile.wp_compiler_dev_mode_on > .ab-item {
						background-color: #a00;
					}
				</style>
				<?php
			}
		}

		public function announce_dev_mode( $mode = false ) {
			$dev_mode = $this->options['dev_mode'];

			return (bool) $dev_mode;
		}

		public static function get_path_vars() {
			$path_variables = array(
				'theme'   => trailingslashit( get_stylesheet_directory() ),
				'parent'  => trailingslashit( get_template_directory() ),
				'plugins' => trailingslashit( WP_PLUGIN_DIR ),
				'uploads' => trailingslashit( self::get_uploads_dir() ),
			);
			$path_variables = apply_filters( 'bbytes_compiler_path_variables', $path_variables );
			$path_variables = apply_filters( 'wp_compiler_path_variables', $path_variables );

			return $path_variables;
		}

		public static function get_uploads_dir() {
			$upload_array = wp_upload_dir();
			if ( ! empty( $upload_array['basedir'] ) ) {
				return $upload_array['basedir'];
			}

			return '';
		}

		protected function redirect_to_referrer() {
			$location = $_SERVER['HTTP_REFERER'];
			if ( ! $location ) {
				$location = admin_url();
			}
			wp_redirect( $location ); // refresh
			die();
		}

		protected function get_options() {
			if ( ! $this->options ) {
				$default_options = array(
					'dev_mode'       => false,
					'folder_hashes'  => array(),
					'output_hashes'  => array(),
					'error_messages' => array(),
				);
				$options         = get_option( 'WP_Compiler_Options', $default_options );
				if ( ! is_array( $options ) ) {
					$options = array();
				}
				$options       = shortcode_atts( $default_options, $options );
				$this->options = $options;
			}

			return $this->options;
		}

		protected function set_option( $key, $val ) {
			$this->options[ $key ] = $val;
			update_option( 'WP_Compiler_Options', $this->options );
		}

		protected function parse_compiler_source_options( $string ) {
			$path_variables = $this->get_path_vars();

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

		protected function get_sourcemap_filenames() {
			$sources = get_option( 'wp_compiler_sources', array() );
			$targets = array();
			if ( ! is_array( $sources ) ) {
				$sources = array();
			}
			$sources = apply_filters( 'wp_compiler_register_sources', $sources );
			if ( ! empty( $sources ) ) {
				foreach ( $sources as $source_item ) {
					if ( ! empty( $source_item['type'] ) && ! empty( $source_item['source'] ) && ! empty( $source_item['target'] ) ) {
						$target = $this->parse_compiler_source_options( $source_item['target'] );
						if ( $source_item['type'] == 'scss' || $source_item['type'] == 'less' ) {
							$targets[] = $this->get_sourcemap_filename_from_file( $target );
						}
					}
				}
			}
			return $targets;
		}

		protected function compile_resources( $force_compile = false ) {
			$sources = get_option( 'wp_compiler_sources', array() );
			if ( ! is_array( $sources ) ) {
				$sources = array();
			}
			$sources = apply_filters( 'bbytes_compiler_register_sources', $sources );
			$sources = apply_filters( 'wp_compiler_register_sources', $sources );
			$options = $this->get_options();
			if ( ! empty( $options['error_messages'] ) ) {
				$force_compile = true;
			}
			if ( ! empty( $sources ) ) {
				foreach ( $sources as $source_item ) {
					if ( ! empty( $source_item['type'] ) && ! empty( $source_item['source'] ) && ! empty( $source_item['target'] ) ) {
						$source = $this->parse_compiler_source_options( $source_item['source'] );
						$target = $this->parse_compiler_source_options( $source_item['target'] );
						if ( $source_item['type'] == 'js' || $source_item['type'] == 'js-manifest' ) {
							$this->compile_js( $source, $target, $force_compile );
						} elseif ( $source_item['type'] == 'scss' ) {
							$this->compile_scss( $source, $target, $force_compile );
						} elseif ( $source_item['type'] == 'less' ) {
							$this->compile_less( $source, $target, $force_compile );
						} else {
							$error = 'unknown source type ' . $source_item['type'];
							$this->set_error_message( $error, 'unknown' );
						}
					}
				}
			}
			$this->set_error_message();
		}

		protected function get_files( $source ) {
			$files_arr = array();
			$files     = glob( $source . '/*.js', GLOB_NOSORT );
			$dirs      = glob( $source . '/*', GLOB_ONLYDIR );

			foreach ( $dirs as $dir ) {
				$files_arr = array_merge( $files_arr, $this->get_files( $dir ) );
			}
			foreach ( $files as $file ) {
				$files_arr[] = $file;
			}

			return $files_arr;
		}

		protected function is_valid_folder( $source ) {
			if ( $source && is_dir( $source ) ) {
				return true;
			}

			return false;
		}

		protected function compile_scss( $source, $target, $force_compile = false ) {
			$directory = dirname( $source );
			if ( is_file( $source ) ) {
				$current_hash = '';
				if ( ! $force_compile ) {
					$current_hash = $this->get_directory_hash( $directory );
				}
				$saved_hash = '';
				if ( isset( $this->options['folder_hashes'][ $directory ]['css'] ) ) {
					$saved_hash = $this->options['folder_hashes'][ $directory ]['css'];
				}
				if ( $force_compile || $saved_hash != $current_hash ) {
					try {
						$scss = new Compiler();
						$scss->setImportPaths( $directory );
						if ( $this->announce_dev_mode() ) {
							$map_target = $this->get_sourcemap_filename_from_file( $target );
							$scss->setSourceMap( Compiler::SOURCE_MAP_FILE );
							$scss->setSourceMapOptions(
								array(
									'sourceRoot'        => '',
									'sourceMapFilename' => null,
									'sourceMapURL'      => null,
									'sourceMapWriteTo'  => $map_target,
									'outputSourceFiles' => false,
									'sourceMapRootpath' => '',
									'sourceMapBasepath' => $directory,
								)
							);
							$formatter = 'ScssPhp\ScssPhp\Formatter\Nested';
						} else {
							$formatter = 'ScssPhp\ScssPhp\Formatter\Compressed';
						}
						$scss->setFormatter( $formatter );
						$compiled_css = apply_filters( 'wp_compiler_compiled_scss', $scss->compile( '@import "' . basename( $source ) . '"' ) );
						$compiled_css = apply_filters( 'wp_compiler_compiled_css', $compiled_css );
					} catch ( Exception $e ) {
						$current_hash = '';
						$error        = str_replace( trailingslashit( ABSPATH ), '', addslashes( $e->getMessage() ) );
						$compiled_css = $this->generate_css_error_message( $error );
						$this->set_error_message( $error, 'scss' );
					}
					ob_start();
					include 'includes/templates/css-header.php';
					$prefix = ob_get_clean() . "\n";
					// TODO -- FILTER PREFIX COMMENT?
					$output_dir = dirname( $target );
					if ( ! is_dir( $output_dir ) ) {
						mkdir( $output_dir, 0755, true );
					}
					$output_hash = hash( 'crc32b', $target );
					$this->options['output_hashes'][ $directory ]['css'] = $output_hash;
					$result = file_put_contents( $target, $prefix . $compiled_css );
					if ( $result === false ) {
						// TODO -- RAISE ERRORS IN A LOGICAL WAY
						$error = 'error writing to file ' . $target;
						$this->set_error_message( $error, 'scss' );
					} else {
						if ( ! isset( $this->options['folder_hashes'][ $directory ] ) ) {
							$this->options['folder_hashes'][ $directory ] = array();
						}
						$this->options['folder_hashes'][ $directory ]['css'] = $current_hash;
						$this->set_option( 'folder_hashes', $this->options['folder_hashes'] );
					}
				}
			} else {
				$error = 'file not found ' . $source;
				$this->set_error_message( $error, 'scss' );
			}
		}

		protected function compile_less( $source, $target, $force_compile = false ) {
			$directory = dirname( $source );
			if ( is_file( $source ) ) {
				$current_hash = '';
				if ( ! $force_compile ) {
					$current_hash = $this->get_directory_hash( $directory );
				}
				$saved_hash = '';
				if ( isset( $this->options['folder_hashes'][ $directory ]['css'] ) ) {
					$saved_hash = $this->options['folder_hashes'][ $directory ]['css'];
				}
				if ( $force_compile || $saved_hash != $current_hash ) {
					try {
						if ( $this->announce_dev_mode() ) {
							$options = array(
								'sourceMap'        => true,
								'sourceMapWriteTo' => $this->get_sourcemap_filename_from_file( $target ),
								'sourceMapURL'     => $this->get_sourcemap_filename_from_file( $target ),
								'cache_dir'        => self::get_uploads_dir() . '/compiler_cache/',
							);
						} else {
							$options = array(
								'compress' => true,
							);
						}
						$less = new Less_Parser( $options );
						$less->parseFile( $source );
						$compiled_css = apply_filters( 'wp_compiler_compiled_less', $less->getCss() );
						$compiled_css = apply_filters( 'wp_compiler_compiled_css', $compiled_css );
					} catch ( Exception $e ) {
						$current_hash = '';
						$error        = str_replace( trailingslashit( ABSPATH ), '', addslashes( $e->getMessage() ) );
						$compiled_css = $this->generate_css_error_message( $error );
						$this->set_error_message( $error, 'less' );
					}
					ob_start();
					include 'includes/templates/css-header.php';
					$prefix = ob_get_clean() . "\n";
					// TODO -- FILTER PREFIX COMMENT?
					$output_dir = dirname( $target );
					if ( ! is_dir( $output_dir ) ) {
						mkdir( $output_dir, 0755, true );
					}
					$output_hash = hash( 'crc32b', $target );
					$this->options['output_hashes'][ $directory ]['css'] = $output_hash;
					$result = file_put_contents( $target, $prefix . $compiled_css );
					if ( $result === false ) {
						// TODO -- RAISE ERRORS IN A LOGICAL WAY
						$error = 'error writing to file ' . $target;
						$this->set_error_message( $error, 'scss' );
					} else {
						if ( ! isset( $this->options['folder_hashes'][ $directory ] ) ) {
							$this->options['folder_hashes'][ $directory ] = array();
						}
						$this->options['folder_hashes'][ $directory ]['css'] = $current_hash;
						$this->set_option( 'folder_hashes', $this->options['folder_hashes'] );
					}
				}
			} else {
				$error = 'file not found ' . $source;
				$this->set_error_message( $error, 'scss' );
			}
		}


		protected function generate_css_error_message( $message ) {
			if ( ! $this->options['dev_mode'] ) {
				return '';
			};
			$message = preg_replace( "/[\r\n]+/", '\a', $message );
			ob_start();
			include 'includes/templates/error-message.php';
			return ob_get_clean();
		}

		protected function compile_js( $source, $target, $force_compile = false ) {
			$directory = dirname( $source );

			if ( file_exists( $source ) ) {

				$current_hash = $this->get_directory_hash( $directory );
				$saved_hash   = '';
				if ( isset( $this->options['folder_hashes'][ $directory ]['js'] ) ) {
					$saved_hash = $this->options['folder_hashes'][ $directory ]['js'];
				}

				if ( $force_compile || $saved_hash != $current_hash ) {
					ob_start();
					include 'includes/templates/js-header.php';
					$prefix = ob_get_clean() . "\n";
					if ( ! $this->options['dev_mode'] && ! apply_filters( 'wp_compiler_skip_minification', false ) ) {
						$files = $this->build_file_list( $source );
						$js    = new MatthiasMullie\Minify\JS();
						foreach ( $files as $file ) {
							$js->add( $file );
						}
						$compiled_js = $js->minify();
						$final_js    = $prefix . $compiled_js;
					} else {
						$raw_js = '';

						if ( is_dir( $source ) ) {
							$files     = $this->get_files( $source );
							$directory = trailingslashit( $source );
							if ( is_array( $files ) && count( $files ) > 0 ) {

								$raw_js = $this->combine_raw_js( $files, $directory );

							}
						} elseif ( is_file( $source ) ) {
							$raw_js = $this->build_raw_js( $source );
						}
						$final_js = $prefix . $raw_js;
					}
					$final_js = apply_filters( 'wp_compiler_final_js_filter', $final_js );

					// TODO -- FILTER PREFIX COMMENT?
					$output_dir = dirname( $target );
					if ( ! is_dir( $output_dir ) ) {
						mkdir( $output_dir, 0775, true );
					}
					$output_hash                                        = hash( 'crc32b', $target );
					$this->options['output_hashes'][ $directory ]['js'] = $output_hash;
					$result = file_put_contents( $target, $final_js );
					if ( $result === false ) {
						// TODO -- RAISE ERRORS IN A LOGICAL WAY
						$error = 'error writing to file ' . $target;
						$this->set_error_message( $error, 'js' );
					} else {
						if ( ! isset( $this->options['folder_hashes'][ $directory ] ) ) {
							$this->options['folder_hashes'][ $directory ] = array();
						}
						$this->options['folder_hashes'][ $directory ]['js'] = $current_hash;
						$this->set_option( 'folder_hashes', $this->options['folder_hashes'] );
					}
				}
			} else {
				$error = 'file not found ' . $source;
				$this->set_error_message( $error, 'js' );
			}
		}

		protected function build_file_list( $source ) {
			$files = array();
			if ( is_dir( $source ) ) {
				$files = $this->get_files( $source );
			} elseif ( is_file( $source ) ) {
				$files = $this->parse_manifest( $source );
			}
			return $files;
		}
		protected function build_raw_js( $manifest ) {
			$directory = trailingslashit( dirname( $manifest ) );
			if ( is_file( $manifest ) ) {
				$files = $this->parse_manifest( $manifest );
			} else {
				// TODO -- RAISE ERRORS IN A LOGICAL WAY
				$error = 'manifest ' . $manifest . " doesn't exist";
				$this->set_error_message( $error, 'js' );
			}
			$raw_js = $this->combine_raw_js( $files, $directory );

			return ( $raw_js );
		}

		protected function parse_manifest( $manifest ) {
			$files     = array();
			$directory = trailingslashit( dirname( $manifest ) );
			$handle    = fopen( $manifest, 'r' );
			if ( $handle ) {
				$line_num = 1;
				// TODO -- MAYBE ADD SUPPORT FOR DIRECTORY WITHOUT MANIFEST
				while ( ( $line = fgets( $handle ) ) !== false ) {
					$line      = trim( $line );
					$full_path = $directory . $line;
					// skip comments and blank lines
					if ( ! $line || $line[0] === '#' ) {
						continue;
					}
					if ( substr( $line, - 3 ) === '.js' ) {
						$file = $directory . $line;
						if ( is_file( $file ) ) {
							$files[] = $file;
						} else {
							$error = 'File ' . $file . " doesn't exist in manifest " . $manifest;
							$this->set_error_message( $error, 'js' );
						}
					} elseif ( substr( $line, - 9 ) === '.manifest' ) {
						$sub_manifest = $directory . $line;
						if ( is_file( $sub_manifest ) ) {
							$sub_files = $this->parse_manifest( $sub_manifest );
							$files     = array_unique( array_merge( $files, $sub_files ) );
						} else {
							// TODO -- RAISE ERRORS IN A LOGICAL WAY
							$error = 'sub-manifest ' . $sub_manifest . " doesn't exist <br>in manifest " . $manifest . '<br>at line ' . $line_num;
							$this->set_error_message( $error, 'js' );
						}
					} // Handle a directory in the manifest file list //
					elseif ( is_dir( $full_path ) ) {
						$files = array_unique( array_merge( $files, $this->get_files( $full_path ) ) );
					} else {
						// TODO -- RAISE ERRORS IN A LOGICAL WAY
						$error = 'manifest ' . $manifest . ' is corrupt on line' . $line_num;
						$this->set_error_message( $error, 'js' );
					}
					$line_num ++;
				}
				fclose( $handle );
			}

			return $files;
		}

		protected function combine_raw_js( $files, $root ) {
			$lines = array();
			if ( $files ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						$fname   = $this->strip_prefix( $file, $root );
						$text    = '/* @@ START FILE: ' . $fname . "*/\n";
						$text   .= file_get_contents( $file ) . "\n";
						$text   .= '/* @@ END FILE: ' . $fname . '*/';
						$lines[] = $text;
					} else {
						// TODO -- RAISE ERRORS IN A LOGICAL WAY
					}
				}
			}

			return implode( "\n\n;\n\n", $lines );
		}

		protected function strip_prefix( $filename, $root ) {
			if ( substr( $filename, 0, strlen( $root ) ) == $root ) {
				$fname = substr( $filename, strlen( $root ) );
			} else {
				$fname = $filename;
			}

			return $fname;
		}

		protected function get_directory_hash( $directory ) {
			if ( ! is_dir( $directory ) ) {
				return false;
			}

			$files = array();
			$dir   = dir( $directory );
			while ( ( $file = $dir->read() ) !== false ) {
				if ( $file != '.' and $file != '..' ) {
					if ( is_dir( $directory . '/' . $file ) ) {
						$files[] = $this->get_directory_hash( $directory . '/' . $file );
					} else {
						$files[] = md5_file( $directory . '/' . $file );
					}
				}
			}
			$dir->close();

			return md5( implode( ',', $files ) );
		}

		protected function set_error_message( $error = '', $type = 'unknown' ) {
			if ( ! is_array( $this->errors ) ) {
				$this->errors = array();
			}
			if ( $error ) {
				$this->errors[] = array(
					'type'    => $type,
					'message' => $error,
				);
			}
			$this->set_option( 'error_messages', $this->errors );
		}

		protected function get_sourcemap_filename_from_file( $file ) {
			return $file . '.map';
		}
	}

	WP_Compiler::Instance();
}
