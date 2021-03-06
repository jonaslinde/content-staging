<?php
namespace Me\Stenberg\Content\Staging\View;

use Me\Stenberg\Content\Staging\Models\Batch;
use WP_List_Table;

class Batch_Table extends WP_List_Table {

	public function __construct() {

		// Set parent defaults.
		parent::__construct( array(
			'singular'  => 'batch',
			'plural'    => 'batches',
			'ajax'      => false
		) );

	}

	/**
	 * Called if a column does not have a method that provides logic for
	 * rendering that column.
	 *
	 * @param Batch $item
	 * @param array $column_name
	 * @return string Text or HTML to be placed inside the column.
	 */
	public function column_default( Batch $item, $column_name ) {
		switch( $column_name ) {
			case 'post_modified':
				return call_user_func( array( $item, 'get_modified' ) );
			default:
				return '';
		}
	}

	/**
	 * Render the 'post_title' column.
	 *
	 * @param Batch $item
	 * @return string HTML to be rendered inside column.
	 */
	public function column_post_title( Batch $item ){

		$edit_link   = admin_url( 'admin.php?page=sme-edit-batch&id=' . $item->get_id() );
		$delete_link = admin_url( 'admin.php?page=sme-delete-batch&id=' . $item->get_id() );

		// Build row actions
		$actions = array(
			'edit'   => '<a href="' . $edit_link . '">Edit</a>',
			'delete' => '<a href="' . $delete_link . '">Delete</a>',
		);

		// Return the title contents.
		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			$edit_link,
			$item->get_title(),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Set the table's columns and titles.
	 *
	 * The column named 'cb' will display checkboxes. Make sure to create a
	 * column_cb method for setting up the checkbox column.
	 *
	 * @return array An associative array:
	 * Key = Column name
	 * Value = Column title (except for key 'cb')
	 */
	public function get_columns() {
		return array(
			'post_title'    => 'Batch Title',
			'post_modified' => 'Modified',
		);
	}

	/**
	 * Make columns sortable.
	 *
	 * @return array An associative array containing sortable columns:
	 * Key = Column name
	 * Value = array( value from database (most likely), bool )
	 */
	public function get_sortable_columns() {
		return array(
			'post_title'    => array( 'post_title', false ),
			'post_modified' => array( 'post_modified', false ),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array An associative array containing bulk actions:
	 * Key: Bulk action slug
	 * Value: Bulk action title
	 */
	public function get_bulk_actions() {
		$actions = array(
			'delete' => 'Delete'
		);
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @see $this->prepare_items()
	 */
	public function process_bulk_action() {

		// Detect when a bulk action is being triggered.
		if ( 'delete' === $this->current_action() ) {
			wp_die( 'Batches deleted!' );
		}

	}

	/**
	 * Prepare batches for being displayed.
	 */
	public function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();
	}

}