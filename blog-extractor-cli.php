<?php

if ( !defined( 'WP_CLI' ) ) return;

/**
 * Blog Extract
 */
class Blog_Extract extends WP_CLI_Command {

	/**
	 * Blog extract
	 *
	 * ## OPTIONS
	 *
	 * <blog-id>
	 * : ID of blog to extract
	 *
	 * [--v]
	 * : Verbose
	 *
	 * [--output]
	 * : Archive name and path
	 *
	 * ## EXAMPLES
	 *
	 *     wp extract blog 3
	 *     wp extract blog 3 --output=/tmp/blog3.tar.gz
	 */
	function blog( $args, $assoc_args ) {
		$v = isset( $assoc_args['v'] );

		if ( ! is_multisite() ) {
			WP_CLI::error( "This is a multisite command only." );
		}
		$blogid = $args[0];

		/* Get the archive name & path */
		if ( isset( $assoc_args['output'] ) ) {
			$path_infos = pathinfo($assoc_args['output']);

			$export_file_path = $path_infos['dirname'];
			$export_file = $path_infos['basename'];
		}
		else {
			$export_file_path = ABSPATH;
			$export_file = "archive-{$blogid}.tar.gz";
		}

		/* check if we can create the archive before going on */
		if ( file_exists($export_file_path . '/' . $export_file) ) {
			WP_CLI::error( "File {$export_file_path}/{$export_file} already exists ! Aborting.." );
		}

		if ( ! is_writeable($export_file_path) ) {
			WP_CLI::error( "$export_file_path is not writeable ! Aborting.." );
		}

		if ( $v ) {
			WP_CLI::line( "Archive will be created at {$export_file_path}/{$export_file}" );
		}

		if ( ! ( $details = get_blog_details( $blogid ) ) ) {
			WP_CLI::error( "Given blog id is invalid." );
			return;
		}
		switch_to_blog( $blogid );

		global $wpdb;

		$blog_tables = $wpdb->tables('blog');

		/************************************\
		                             DATABASE
		\************************************/
		 /*
		  * We use the $rename_tables array to store any tables that will need to renamed upon import to the new database.
		  */
		$rename_tables = array();

		/*
		 * For blog ID 1, we have to use a different temp user table name, since $wpdb->prefix doesn't have a number
		 * appended and we don't want to effect the global user tables.
		 */
		if ( 1 == $blogid ) {
			$tmp_users = "{$wpdb->prefix}temp_users";
			$tmp_usermeta = "{$wpdb->prefix}temp_usermeta";

			// Add these to the rename tables array, so we can rename them when importing to the new database
			$rename_tables[ $tmp_users ] = "{$wpdb->prefix}users";
			$rename_tables[ $tmp_usermeta ] = "{$wpdb->prefix}usermeta";
		} else {
			$tmp_users = "{$wpdb->prefix}users";
			$tmp_usermeta = "{$wpdb->prefix}usermeta";
		}

		$blog_1_case = ! empty( $rename_tables ); // just a nice flag to use later

		$blog_tables['users'] = $tmp_users;
		$blog_tables['usermeta'] = $tmp_usermeta;

		// @debug let's take a look
		// print_r( $blog_tables );

		$users = wp_list_pluck( get_users(), 'ID' );
		$super_admin_ids = array();
		foreach ( get_super_admins() as $username ) {
			$super_admin_ids[] = get_user_by( 'login', $username )->ID;
		}

		$supes = array_diff( $super_admin_ids, $users );
		$users = array_filter( array_unique( array_merge( $users, $super_admin_ids ) ) );

		$userlist = implode( ',', $users );

		if ( $v ) {
			WP_CLI::line( 'Begin copying user tables' );
		}

		/*
		 * Checks if we are attempting to create a temp table with the same name as the main user table. If so, bail.
		 * Currently happens if attempting to export blog ID 1, since the DB prefix will not have a number appended.
		 */
		if ( $tmp_users == $wpdb->users ) {
			// OMG run away, FAST before we break something important
			WP_CLI::error( 'There was an error duplicating user tables' );
		}

		// duplicate global user tables
		// delete unnecessary rows (probably not performant on large data)
		$wpdb->query( "create table if not exists {$tmp_users} like {$wpdb->users}" );
		$check = $wpdb->get_col( "select * from {$tmp_users}" );
		if ( empty( $check ) ) {
			if ( $v ) {
				WP_CLI::line( 'copying main users table' );
			}
			$wpdb->query( "insert into {$tmp_users} select * from {$wpdb->users} where ID IN ({$userlist})" );
		}

		$wpdb->query( "create table if not exists {$tmp_usermeta} like {$wpdb->usermeta}" );
		$check = $wpdb->get_col( "select * from {$tmp_usermeta}" );
		if ( empty( $check ) ) {
			if ( $v ) {
				WP_CLI::line( 'copying main usermeta table' );
			}
			$wpdb->query( "insert into {$tmp_usermeta} select * from {$wpdb->usermeta} where user_id IN ({$userlist})" );
		}

		// for the super admins that were not specifically added to the blog on the network, give administrator role
		foreach ( $supes as $sid ) {
			$wpdb->insert( $tmp_usermeta,
				array(
					'user_id'    => $sid,
					'meta_key'   => $wpdb->prefix .'capabilities',
					'meta_value' => serialize( array( 'administrator' => true ) ),
				),
				array(
					'%d',
					'%s',
					'%s',
				)
			);
		}

		if ( $v ) {
			WP_CLI::line( 'Begin exporting tables' );
		}
		$tablelist = implode( ' ', $blog_tables );
		$sql_file = $export_file_path.'/database.sql';
		shell_exec( "mysqldump -h " . DB_HOST . " -u ". DB_USER ." -p". DB_PASSWORD ." ". DB_NAME ." {$tablelist} > {$sql_file}" );

		if ( file_exists( $sql_file ) ) {
			if ( ( $filesize = filesize( $sql_file ) ) > 0 ) {
				// Add statements to rename any tables we have in the $rename_tables array
				if ( $blog_1_case ) {
					$sql_fh = fopen( $sql_file, 'a' );
					fwrite( $sql_fh, "\n" );
					foreach ( $rename_tables as $oldname => $newname ) {
						fwrite( $sql_fh, "RENAME TABLE `{$oldname}` TO `{$newname}`;\n" );
					}
					fclose( $sql_fh );
				}

				$filesize = size_format( $filesize, 2 );
				if ( $v ) {
					WP_CLI::line( 'Database tables exported' );
				}
				$wpdb->query( "drop table if exists {$tmp_users}, {$tmp_usermeta}" );
			} else {
				unlink( $sql_file );
				WP_CLI::error( 'There was an error exporting the archive.' );
			}
		} else {
			WP_CLI::error( 'There was an error exporting the archive.' );
		}

		/************************************\
		                                FILES
		\************************************/

		$export_dirs = array( $sql_file );

		// uploads
		$upload_dir = wp_upload_dir();
		$export_dirs[] = $upload_dir['basedir'];

		// plugins
		$plugins = get_option( 'active_plugins' );
		$plugins = array_map( function($i) {
			$parts = explode( '/', $i );
			$root = array_shift( $parts );
			return WP_CONTENT_DIR .'/plugins/'. $root;
		}, $plugins );
		$export_dirs = array_merge( $export_dirs, $plugins );

		// network plugins
		$networkplugins = wp_get_active_network_plugins();
		$networkplugins = array_map( function($i) {
			$parts = explode( '/', str_replace( WP_CONTENT_DIR .'/plugins/', '', $i ) );
			$root = array_shift( $parts );
			return WP_CONTENT_DIR .'/plugins/'. $root;
		}, $networkplugins );
		$export_dirs = array_merge( $export_dirs, $networkplugins );

		// mu plugins
		if ( is_dir(ABSPATH . MUPLUGINDIR) ) {
			$export_dirs[] = ABSPATH . MUPLUGINDIR;
		}

		// mu plugins
		$dropins = array_keys( get_dropins() );
		$dropins = array_map( function($i) {
			return WP_CONTENT_DIR .'/'. $i;
		}, $dropins );
		$export_dirs = array_merge( $export_dirs, $dropins );


		// theme(s)
		$themes = array_unique( array( get_stylesheet(), get_template() ) );
		$themes = array_map( function($i) { return WP_CONTENT_DIR. get_raw_theme_root( get_stylesheet() ) .'/' . $i;}, $themes );
		$export_dirs = array_merge( $export_dirs, $themes );

		// remove ABSPATH. makes the export more friendly when extracted
		$exports = array_map( function($i) {
			return '"'. str_replace( ABSPATH, '', $i ) .'"';
		}, $export_dirs );

		// @debug let's take a look
		// print_r( $exports );

		// work out any directories that should be excluded from the archive
		$exclude = '';

		if ( $blog_1_case ) {
			// if we renamed, we're on site ID 1, which also means uploads aren't in /sites/
			$exclude_exports[] = str_replace( ABSPATH, '', $upload_dir['basedir'] ) . '/sites';
		}

		if ( isset( $exclude_exports ) ) {
			foreach ( $exclude_exports as $ee ) {
				$exclude .= " --exclude=$ee ";
			}
		}

		// @debug let's take a look
		// print_r( $exclude_exports );

		// GOOD!
		$abspath = ABSPATH;
		$exports = implode( ' ', $exports );
		if ( $v ) {
			WP_CLI::line( 'Begin archiving files' );
		}
		shell_exec( "tar -cf {$export_file_path}/{$export_file} -C {$abspath} {$exports} {$exclude}" );

		if ( file_exists( $export_file_path . '/' . $export_file ) ) {
			if ( ( $filesize = filesize( $export_file_path . '/' . $export_file ) ) > 0 ) {
				// sql dump was archived, remove regular file
				unlink( $sql_file );

				$filesize = size_format( $filesize, 2 );
				WP_CLI::success( "$export_file created in $export_file_path! ($filesize)" );

				$prefix = WP_CLI::colorize( "%P{$wpdb->prefix}%n" );
				WP_CLI::line( 'In your new install in wp-config.php, set the $table_prefix to '. $prefix );
				WP_CLI::line( 'You\'ll also need to do a search-replace for the url change' );

				$old_url = untrailingslashit( $details->domain. $details->path );
				WP_CLI::line( '=========================================' );

				WP_CLI::line( "# update URLs" );
				WP_CLI::line( "wp search-replace {$old_url} NEWURL" );
				if ( ! $blog_1_case ) {
					// again, we're on ID 1, so uploads aren't in sites, so no need for these find-replace recommendations
					$rel_upl = str_replace( ABSPATH, '', $upload_dir['basedir'] );
					WP_CLI::line( "# move the uploads to the typical directory" );
					WP_CLI::line( "mv {$rel_upl}/* wp-content/uploads/" );
					WP_CLI::line( "# remove the old directory" );
					WP_CLI::line( "rm -rf wp-content/uploads/sites/" );
					WP_CLI::line( "# update database" );
					WP_CLI::line( "wp search-replace {$rel_upl}/ wp-content/uploads/" );
				}

				WP_CLI::line( '=========================================' );


			} else {
				unlink( $export_file_path . '/' . $export_file );
				WP_CLI::error( 'There was an error creating the archive.' );
			}
		} else {
			WP_CLI::error( 'Unable to create the archive.' );
		}

	}

}

WP_CLI::add_command( 'extract', 'Blog_Extract' );
