=== WP Compiler ===
Contributors: burlingtonbytes gschoppe
Tags: sass, scss, less, compiled, styles, workflow, build tools, minified, minify, uglify, uglified, combined, manifest, include, enqueue, grunt, gulp, webpack
Requires at least: 4.8
Tested up to: 5.0
Requires PHP: 5.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
The power of preprocessed CSS and minified JS in your theme or plugin, without any complicated installs or build tools.
 
== Description ==

Harness the power of pre-processed CSS and minified JS in your theme or plugin, without any complicated installs or build tools. Simply tell WP Compiler where to find your source files and where to put the compiled results, then throw your install into Dev Mode. WP Compiler watches your source folders for you, and recompiles your CSS and JS on any file change. WP Compiler supports both SCSS and LESS precompilers for CSS, to suit anyone's preference.

When you're ready to launch a new site, just turn off Dev Mode, and your styles and javascript will be compiled & minimized and comments & source maps will be removed. With Dev mode disabled, Compiler will stop watching source directories, so there is no effect on site performance, but you can still apply a quick change at any time, by clicking the compile button in the admin toolbar.

WP Compiler relies on [scssphp](https://scssphp.github.io/scssphp/), [lessphp](http://lessphp.typesettercms.com/), and [minify](https://www.minifier.org/).
Specific issues with the underlying compilation libraries should be submitted to their respective developers.

== Installation ==
 
1. Download the plugin file to your system and unzip it
1. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's wp-content/plugins/ directory
1. Activate the plugin from the Plugins menu within the WordPress admin
1. Go to Settings -> Compiler Settings
1. Set the paths to your source CSS and JS files and the targets they compile to
1. Turn on Dev Mode and get coding!

== Frequently Asked Questions ==
 
= How can I combine multiple JavaScript files? =

The easiest way to compile your JS is to store it all in a single directory. If you set your compilation source to be the directory path, all JS files in the directory will be combined and minified.

= How can I make sure JavaScript files are minified in a specific order? =

WP Compiler supports `.manifest` files for JavaScript. This is a custom file format in which each line consists of a relative file path to a JS file, a relative path to a directory containing JS, or a relative path to another `.manifest` file. for clarity, lines beginning with a hash symbol (#) are treated as comments.

Here is an example of a manifest file:

>    # <js.manifest>
>    # This is a sample JavaScript manifest file for WP Compiler
>    # all paths are relative to the current manifest file
>    # First we are going to load specific files that have to come first
>    test-script.js
>    test-script2.js
>    # Now let's load a sub-manifest
>    partials/js.manifest
>    # Finally, let's load a folder whose contents
>    # don't need to be in a specific order
>    external-scripts/

= What about AutoPrefixing, JS Transpiling, NPM includes, Require.js, Custom Task Runners or <insert feature here>? =

Unfortunately, there is a limit to how many of the immense number of node.js build processes available can be replicated in native PHP. Please let us know about which features you'd most like to see tackled next.

== Screenshots ==
 
1. The admin bar interface of WP Compiler
2. The admin bar interface of WP Compiler, in dev mode
3. The settings page
 
== Changelog ==
 
= 1.0 =
* Initial Release