<?php

/**
 * Plugin Name: Defer CSS And JavaScript
 * Description: This plugin defers CSS and JavaScript files so that your website loads faster.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Team Performance
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Defer CSS And JavaScript is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Defer CSS And JavaScript is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Defer CSS And JavaScript. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 **/

function dcaj_hook_buffer( $buffer )
{
	$buffer = apply_filters( 'dcaj_buffer', $buffer );
	return $buffer;
}

function dcaj_start_buffer() {
	if ( ! is_admin() && strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) === false && strpos( $_SERVER['REQUEST_URI'], 'wp-signup.php' ) === false ) {
		ob_start( 'dcaj_hook_buffer' );
	}
}
add_action( 'after_setup_theme', 'dcaj_start_buffer' );


function dcaj_defer_scripts( $buffer )
{
	preg_match_all( '/<script.*>/iU', $buffer, $matches ); //this will match direct script tags as well as linked script files

	if ( ! isset( $matches ) ) {
		return $buffer;
	}

	$scripts = $matches[0];
	
	$options = get_option( 'dcaj_api_settings' );
	$defer_inline_scripts = ( ! isset( $options['dcaj_api_defer_inline_javascripts_field'] ) || empty( $options['dcaj_api_defer_inline_javascripts_field'] ) ) ? false : true;
	$skip_jquery = ( ! isset( $options['dcaj_api_exclude_jquery_field'] ) || empty( $options['dcaj_api_exclude_jquery_field'] ) ) ? false : true;
	$exclude_js_field = ( isset( $options['dcaj_api_exclude_javascripts_field'] ) ) ? $options['dcaj_api_exclude_javascripts_field'] : '';
	$exclude_scripts = explode( ',' , $exclude_js_field );

	foreach ( $scripts as $index => $script ) {
		$attributes_and_tags = preg_split ('/\s+/', $script );
		preg_match( "/.*jquery[0-9-_.]*?(min)?\.js/", $script, $jquery_matches );

		$is_script_excluded = false;
		foreach ($exclude_scripts as $excluded_script) {
			if ( strpos( $script, trim( $excluded_script ) ) > 0) { //using strpos instead of in_array so that admin user can skin version from the links
				$is_script_excluded = true;
				break;
			}
		}

		//don't add defer if the script already has async or defer
		if ( in_array( 'defer', $attributes_and_tags ) || in_array( 'defer>', $attributes_and_tags ) || in_array( 'defer/>', $attributes_and_tags ) || in_array( 'async', $attributes_and_tags ) || in_array( 'async>', $attributes_and_tags ) || in_array( 'async/>', $attributes_and_tags ) ) {
			continue;
		} elseif( $skip_jquery && count( $jquery_matches ) > 0) {
			continue;
		} elseif( $is_script_excluded ) {
			continue;
		} else {
			$deferred_script = str_replace( '>', ' defer>', $script );
			//defer inline scripts
			if( ! ( stripos( $script, 'src' ) > -1) && $defer_inline_scripts ) {
				$pattern = "/" . str_replace( "/", "\/", preg_quote( $script ) ) . ".*?\<\/(\s)*script\>/s"; // forward slash is not a special character and hence preg_quote does not add escape character to it
				// 's' flag also matches dots to new line characters
				preg_match( $pattern, $buffer, $inline_script );
				$inline_script = $inline_script[0];
				$script_without_tags = trim( preg_replace( "/\<\/(\s)*script\>/", '', str_replace( $script, '', $inline_script ) ) );
				$base64_script_wihout_tags = base64_encode( $script_without_tags );
				$base64_script = '<script src="data:text/javascript;base64,' . $base64_script_wihout_tags . '" defer></script>';
				$buffer = str_replace( $inline_script, $base64_script , $buffer );
			} else {
				$buffer = str_replace( $script, $deferred_script, $buffer );
			}
		}
	}

	return $buffer;
}
add_action( 'dcaj_buffer', 'dcaj_defer_scripts' );

function dcaj_defer_styles( $buffer )
{
	preg_match_all( "/<link(.*)rel\s*=\s*('|\")stylesheet(.*)>/i", $buffer, $matches );
	$styles = $matches[0];
	$styles_to_be_appended_to_body = '';
	$options = get_option( 'dcaj_api_settings' );
	$dcaj_api_exclude_css_field = ( isset( $options['dcaj_api_exclude_css_field'] ) ) ? $options['dcaj_api_exclude_css_field'] : '';
	$excluded_styles = explode( ',', $dcaj_api_exclude_css_field );
	foreach( $styles as $style ) {

		$is_style_excluded = false;
		foreach ( $excluded_styles as $excluded_style ) {
			if ( strpos( $style, trim( $excluded_style ) ) > 0) { //using strpos instead of in_array so that admin user can skin version from the links
				$is_style_excluded = true;
				break;
			}
		}
		if( $is_style_excluded ) {
			continue;
		}
		$updated_style = str_replace( 'stylesheet', 'preload', $style );
		$updated_style = preg_replace( "/\/*>/", " as=\"style\" />", $updated_style );
		$updated_style = preg_replace( "/\/*>/", " onload=\"this.onload=null;this.rel='stylesheet';\" />", $updated_style );
		$buffer = str_replace( $style, $updated_style, $buffer );
		$styles_to_be_appended_to_body .= '<noscript>' . $style . '</noscript>';
	}
	$load_css_script = '
	<script>
		/*! loadCSS. [c]2017 Filament Group, Inc. MIT License */
		/* This file is meant as a standalone workflow for
		- testing support for link[rel=preload]
		- enabling async CSS loading in browsers that do not support rel=preload
		- applying rel preload css once loaded, whether supported or not.
		*/
		(function( w ){
			"use strict";
			// rel=preload support test
			if( !w.loadCSS ){
				w.loadCSS = function(){};
			}
			// define on the loadCSS obj
			var rp = loadCSS.relpreload = {};
			// rel=preload feature support test
			// runs once and returns a function for compat purposes
			rp.support = (function(){
				var ret;
				try {
					ret = w.document.createElement( "link" ).relList.supports( "preload" );
				} catch (e) {
					ret = false;
				}
				return function(){
					return ret;
				};
			})();

			// if preload isn\'t supported, get an asynchronous load by using a non-matching media attribute
			// then change that media back to its intended value on load
			rp.bindMediaToggle = function( link ){
				// remember existing media attr for ultimate state, or default to \'all\'
				var finalMedia = link.media || "all";

				function enableStylesheet(){
					// unbind listeners
					if( link.addEventListener ){
						link.removeEventListener( "load", enableStylesheet );
					} else if( link.attachEvent ){
						link.detachEvent( "onload", enableStylesheet );
					}
					link.setAttribute( "onload", null ); 
					link.media = finalMedia;
				}

				// bind load handlers to enable media
				if( link.addEventListener ){
					link.addEventListener( "load", enableStylesheet );
				} else if( link.attachEvent ){
					link.attachEvent( "onload", enableStylesheet );
				}

				// Set rel and non-applicable media type to start an async request
				// note: timeout allows this to happen async to let rendering continue in IE
				setTimeout(function(){
					link.rel = "stylesheet";
					link.media = "only x";
				});
				// also enable media after 3 seconds,
				// which will catch very old browsers (android 2.x, old firefox) that don\'t support onload on link
				setTimeout( enableStylesheet, 3000 );
			};

			// loop through link elements in DOM
			rp.poly = function(){
				// double check this to prevent external calls from running
				if( rp.support() ){
					return;
				}
				var links = w.document.getElementsByTagName( "link" );
				for( var i = 0; i < links.length; i++ ){
					var link = links[ i ];
					// qualify links to those with rel=preload and as=style attrs
					if( link.rel === "preload" && link.getAttribute( "as" ) === "style" && !link.getAttribute( "data-loadcss" ) ){
						// prevent rerunning on link
						link.setAttribute( "data-loadcss", true );
						// bind listeners to toggle media back
						rp.bindMediaToggle( link );
					}
				}
			};

			// if unsupported, run the polyfill
			if( !rp.support() ){
				// run once at least
				rp.poly();

				// rerun poly on an interval until onload
				var run = w.setInterval( rp.poly, 500 );
				if( w.addEventListener ){
					w.addEventListener( "load", function(){
						rp.poly();
						w.clearInterval( run );
					} );
				} else if( w.attachEvent ){
					w.attachEvent( "onload", function(){
						rp.poly();
						w.clearInterval( run );
					} );
				}
			}


			// commonjs
			if( typeof exports !== "undefined" ){
				exports.loadCSS = loadCSS;
			}
			else {
				w.loadCSS = loadCSS;
			}
		}( typeof global !== "undefined" ? global : this ) );
	</script>
	';
	$buffer = preg_replace( "/<\/\s*body>/", $styles_to_be_appended_to_body . $load_css_script . '</body>', $buffer );
	return $buffer;
}
add_action( 'dcaj_buffer', 'dcaj_defer_styles' );

/****** Admin Panel Functionality Starts From Here ******/

function dcaj_api_settings_init(  ) {
	register_setting( 'dcaj_plugin', 'dcaj_api_settings' );
	add_settings_section(
		'dcaj_api_dcaj_css_section',
		__( 'CSS Settings', 'critical-css' ),
		'dcaj_api_settings_section_callback',
		'dcaj_plugin'
	);

	add_settings_field(
		'dcaj_api_exclude_css_field',
		__( 'Exclude CSS (critical css)<br /><small><i style="font-weight: normal;">These CSS links will not be deferred</i></small>', 'critical-css' ),
		'dcaj_api_exclude_css_field_render',
		'dcaj_plugin',
		'dcaj_api_dcaj_css_section'
	);

	add_settings_section(
		'dcaj_api_dcaj_javascript_section',
		__( 'JavaScript Settings', 'critical-css' ),
		'dcaj_api_settings_section_callback',
		'dcaj_plugin'
	);
	add_settings_field(
		'dcaj_api_defer_inline_javascripts_field',
		__( 'Defer Inline JavaScripts:', 'critical-css' ),
		'dcaj_api_defer_inline_javascripts_field_render',
		'dcaj_plugin',
		'dcaj_api_dcaj_javascript_section'
	);
	add_settings_field(
		'dcaj_api_exclude_jquery_field',
		__( 'Don\'t Defer jQuery:', 'critical-css' ),
		'dcaj_api_exclude_jquery_field_render',
		'dcaj_plugin',
		'dcaj_api_dcaj_javascript_section'
	);
	add_settings_field(
		'dcaj_api_exclude_javascripts_field',
		__( 'Exclude Scripts:<br /><small><i style="font-weight: normal;">These scripts links will not be deferred</i>', 'critical-css' ),
		'dcaj_api_exclude_javascripts_field_render',
		'dcaj_plugin',
		'dcaj_api_dcaj_javascript_section'
	);

	if ( get_option( 'dcaj_api_settings' ) === false ) {
		update_option( 'dcaj_api_settings', array(
			'dcaj_api_defer_inline_javascripts_field' => 1
		) );
	}
}
add_action( 'admin_init', 'dcaj_api_settings_init' );

function dcaj_api_exclude_css_field_render(  ) {
	$options = get_option( 'dcaj_api_settings' );
	$exclude_css = ( isset( $options['dcaj_api_exclude_css_field'] ) ) ? $options['dcaj_api_exclude_css_field'] : '';
	?>
	<textarea rows="5" name='dcaj_api_settings[dcaj_api_exclude_css_field]'  cols="40" placeholder="Comma separated list of CSS links"><?php echo $exclude_css; ?></textarea>
	<?php
}

function dcaj_api_defer_inline_javascripts_field_render(  ) {
	$options = get_option( 'dcaj_api_settings' );
	$dcaj_api_defer_inline_javascripts_field = ( isset( $options['dcaj_api_defer_inline_javascripts_field'] ) ) ? $options['dcaj_api_defer_inline_javascripts_field'] : true;
	?>
	<input type="checkbox" name='dcaj_api_settings[dcaj_api_defer_inline_javascripts_field]'  value='1' <?php checked( 1, $dcaj_api_defer_inline_javascripts_field, true ); ?>  />
	<?php
}

function dcaj_api_exclude_jquery_field_render(  ) {
	$options = get_option( 'dcaj_api_settings' );
	$dcaj_api_exclude_jquery_field = ( isset( $options['dcaj_api_exclude_jquery_field'] ) ) ? $options['dcaj_api_exclude_jquery_field'] : false;
	?>
	<input type="checkbox" name='dcaj_api_settings[dcaj_api_exclude_jquery_field]'  value='1' <?php checked( 1, $dcaj_api_exclude_jquery_field, true ); ?>  />
	<?php
}

function dcaj_api_exclude_javascripts_field_render(  ) {
	$options = get_option( 'dcaj_api_settings' );
	$dcaj_api_exclude_javascripts_field = ( isset( $options['dcaj_api_exclude_javascripts_field'] ) ) ? $options['dcaj_api_exclude_javascripts_field'] : '';
	?>
	<textarea rows="5" name='dcaj_api_settings[dcaj_api_exclude_javascripts_field]' cols="40" placeholder="Comma separated list of JavaScript links"><?php echo $dcaj_api_exclude_javascripts_field; ?></textarea>
	<?php
}

function dcaj_api_settings_section_callback( ) {
	//echo __( 'This Section Description', 'wordpress' );
}

//Adding Admin Setting Page
function dcaj_setup_menu(){
	add_submenu_page( 
		'tools.php',
		__( 'Defer CSS & JavaScript Page', 'critical-css' ),
		__( 'Defer CSS & JS', 'critical-css' ),
		'manage_options',
		'defer-css-and-js',
		'dcaj_setting_page'
	);
}
add_action( 'admin_menu', 'dcaj_setup_menu' );

function dcaj_plugin_add_settings_link( $links ) {
	$settings_link = '<a href="tools.php?page=defer-css-and-js">' . __( 'Settings' ) . '</a>';
	array_push( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'dcaj_plugin_add_settings_link' );

function dcaj_setting_page() {
	settings_errors(); ?>
	<form action='options.php' method='POST'>
		<h1><?php _e( 'Defer CSS And JavaScript', 'critical-css' ); ?></h1>
		<?php
		settings_fields( 'dcaj_plugin' );
		do_settings_sections( 'dcaj_plugin' );
		submit_button();
		?>
	</form>
	<?php
}