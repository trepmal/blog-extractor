<?php
/*
 * Plugin Name: Blog Extractor
 * Plugin URI: https://github.com/trepmal/blog-extractor
 * Description: WP-CLI command for extracting a single blog from a multisite network
 * Version: 1
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain:
 * DomainPath:
 * Network:
 */

if ( defined('WP_CLI') && WP_CLI ) {
	include plugin_dir_path( __FILE__ ) . '/blog-extractor-cli.php';
}