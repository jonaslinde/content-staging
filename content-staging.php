<?php
/**
 * Plugin Name: Content Staging
 * Plugin URI: http://stenberg.me/
 * Description: Content Staging.
 * Author: Joakim Stenberg
 * Version: 1.0
 * Author URI: http://stenberg.me/
 * License: GPLv2
 */

/**
 * Copyright 2014 Joakim Stenberg (email: stenberg.me@gmail.com)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
 * Include files.
 */
require_once( ABSPATH . WPINC . '/class-IXR.php' );
require_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
require_once( 'classes/controllers/class-batch-ctrl.php' );
require_once( 'classes/db/mappers/class-mapper.php' );
require_once( 'classes/db/mappers/class-batch-mapper.php' );
require_once( 'classes/db/mappers/class-post-mapper.php' );
require_once( 'classes/db/mappers/class-taxonomy-mapper.php' );
require_once( 'classes/db/mappers/class-term-mapper.php' );
require_once( 'classes/db/mappers/class-user-mapper.php' );
require_once( 'classes/db/class-dao.php' );
require_once( 'classes/db/class-batch-dao.php' );
require_once( 'classes/db/class-post-dao.php' );
require_once( 'classes/db/class-postmeta-dao.php' );
require_once( 'classes/db/class-term-dao.php' );
require_once( 'classes/db/class-user-dao.php' );
require_once( 'classes/managers/class-batch-mgr.php' );
require_once( 'classes/models/class-batch.php' );
require_once( 'classes/models/class-post.php' );
require_once( 'classes/models/class-taxonomy.php' );
require_once( 'classes/models/class-term.php' );
require_once( 'classes/models/class-user.php' );
require_once( 'classes/patterns/interface-observable.php' );
require_once( 'classes/patterns/interface-observer.php' );
require_once( 'classes/resources/class-receive-batch.php' );
require_once( 'classes/view/class-batch-table.php' );
require_once( 'classes/view/class-post-table.php' );
require_once( 'classes/xmlrpc/class-client.php' );
require_once( 'classes/class-api.php' );
require_once( 'classes/class-background-process.php' );
require_once( 'classes/class-import-batch.php' );
require_once( 'classes/class-setup.php' );
require_once( 'classes/view/class-template.php' );
require_once( 'functions/helpers.php' );

/*
 * Import classes.
 */
use Me\Stenberg\Content\Staging\API;
use Me\Stenberg\Content\Staging\Import_Batch;
use Me\Stenberg\Content\Staging\Setup;
use Me\Stenberg\Content\Staging\View\Template;
use Me\Stenberg\Content\Staging\Controllers\Batch_Ctrl;
use Me\Stenberg\Content\Staging\DB\Mappers\Batch_Mapper;
use Me\Stenberg\Content\Staging\DB\Mappers\Post_Mapper;
use Me\Stenberg\Content\Staging\DB\Mappers\Taxonomy_Mapper;
use Me\Stenberg\Content\Staging\DB\Mappers\Term_Mapper;
use Me\Stenberg\Content\Staging\DB\Mappers\User_Mapper;
use Me\Stenberg\Content\Staging\DB\Batch_DAO;
use Me\Stenberg\Content\Staging\DB\Post_DAO;
use Me\Stenberg\Content\Staging\DB\Postmeta_DAO;
use Me\Stenberg\Content\Staging\DB\Term_DAO;
use Me\Stenberg\Content\Staging\DB\User_DAO;
use Me\Stenberg\Content\Staging\Managers\Batch_Mgr;
use Me\Stenberg\Content\Staging\Resources\Receive_Batch;
use Me\Stenberg\Content\Staging\XMLRPC\Client;

/**
 * Class Content_Staging
 */
class Content_Staging {

	/**
	 * Actions performed during plugin activation.
	 */
	public static function activate() {
	}

	/**
	 * Actions performed during plugin deactivation.
	 */
	public static function deactivate() {
	}

	/**
	 * Initialize the plugin.
	 */
	public static function init() {

		global $wpdb;
		global $sme_content_staging_api;

		// Determine plugin URL and plugin path of this plugin.
		$plugin_path = dirname( __FILE__ );
		$plugin_url  = plugins_url( basename( $plugin_path ), $plugin_path );

		// Database mappers
		$batch_mapper    = new Batch_Mapper();
		$post_mapper     = new Post_Mapper();
		$taxonomy_mapper = new Taxonomy_Mapper();
		$term_mapper     = new Term_Mapper();
		$user_mapper     = new User_Mapper();

		// Data access objects.
		$batch_dao    = new Batch_DAO( $wpdb, $batch_mapper );
		$post_dao     = new Post_DAO( $wpdb, $post_mapper );
		$postmeta_dao = new Postmeta_DAO( $wpdb );
		$term_dao     = new Term_DAO( $wpdb, $taxonomy_mapper, $term_mapper );
		$user_dao     = new User_DAO( $wpdb, $user_mapper );

		// XML-RPC client.
		$xmlrpc_client = new Client( CONTENT_STAGING_REMOTE_SERVER, CONTENT_STAGING_SECRET_KEY );

		// Managers.
		$batch_mgr = new Batch_Mgr( $batch_dao, $post_dao, $postmeta_dao, $term_dao, $user_dao );

		// Template engine.
		$template = new Template( dirname( __FILE__ ) . '/templates/' );

		// Controllers / Resources.
		$batch_ctrl    = new Batch_Ctrl( $template, $batch_mgr, $xmlrpc_client, $batch_dao, $post_dao );
		$receive_batch = new Receive_Batch( $batch_dao, $post_dao );

		// APIs.
		$sme_content_staging_api = new API( $post_dao, $postmeta_dao );

		// Attach observers to the XML-RPC client.
		$xmlrpc_client->attach( $receive_batch );

		// Controller responsible for importing a batch to production.
		$import_batch = new Import_Batch( $post_dao, $postmeta_dao, $term_dao, $user_dao );

		// Plugin setup.
		$setup = new Setup( $batch_ctrl, $xmlrpc_client, $plugin_url );

		// Actions.
		add_action( 'init', array( $setup, 'register_post_type' ) );
		add_action( 'admin_menu', array( $setup, 'register_menu_pages' ) );
		add_action( 'admin_notices', array( $setup, 'quick_deploy_batch' ) );
		add_action( 'admin_enqueue_scripts', array( $setup, 'load_assets' ) );
		add_action( 'sme_import_batch', array( $import_batch, 'init' ) );

		// Filters.
		add_filter( 'xmlrpc_methods', array( $setup, 'register_xmlrpc_method' ) );
		add_filter( 'sme_postmeta_post_relation_keys', array( $setup, 'set_postmeta_post_relation_keys' ) );
	}

}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Content_Staging', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Content_Staging', 'deactivate' ) );

// Initialize plugin.
add_action( 'plugins_loaded', array( 'Content_Staging', 'init' ) );
