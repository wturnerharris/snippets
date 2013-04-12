<?php

if(!class_exists('WP_List_Table')) require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

/**
 * Extended class for displaying a list of items in an HTML table.
 *
 * @usage
 *		// be sure to modify the display_rows function to customize the output of the rows
 *		$args = array(
 *			'columns' => array(
 *				'cb'=>__('ID','textdomain'),
 *				'col_last_name'=>__('Last Name','textdomain'),
 *				'col_first_name'=>__('First Name','textdomain'),
 *			),
 *			'sort_columns' => array(
 *				'col_first_name'=>array('first_name', true),
 *				'col_last_name'=>array('last_name', true),
 *			),
 *			'bulk_actions' => array(
 *				"delete" => "Delete Permanently",
 *				"approve" => "Approve",
 *				"reject" => "Reject",
 *			),
 *			'table_name' => 'table_name', // select all from this table
 *			'where' => 'WHERE_CLAUSE', // add WHERE clause without the WHERE
 *			'query' => 'CUSTOM_QUERY, // overrides where and table_name
 *			'search' => array( 'searchable_columns' )
 *		);
 *		$wp_list_table = new Review_List_Table($args);
 *		$wp_list_table->prepare_items();
 *		$wp_list_table->display();
 * @endusage
 */
class Review_List_Table extends WP_List_Table {
	var $_sort_columns, $_bulk_actions, $_query, $_where, $_search, $_table_name;
	
	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	function __construct( $args ) {
		parent::__construct( array(
			'singular'=> 'wp_review_item', //Singular label
			'plural' => 'wp_review_items', //plural label, also this well be one of the table css class
			'ajax'	=> false //We won't support Ajax for this table
		) );
		if ( is_null($args) ) return false;
		if ( is_array($args) ) {
			foreach ($args as $var => $val) $this->{"_".$var} = $val;
		}
	}

	/**
	 * Add extra markup in the toolbars before or after the list
	 * @param string $which, helps you decide if you add the markup after (bottom) or before (top) the list
	 */
	function extra_tablenav( $which ) {
		$search = @$_POST['s']?esc_attr($_POST['s']):"";
		if ( $which == "top" ) : ?>
		<div class="actions">
			<p class="search-box">
				<label for="post-search-input" class="screen-reader-text">Search Pages:</label>
				<input type="search" value="<?php echo $search; ?>" name="s" id="post-search-input">
				<input type="submit" value="Search" class="button" id="search-submit" name="">
			</p>
		</div>
		<?php endif;
	}

	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
		return $this->_columns;
	}

	/**
	 * Decide which columns to activate the sorting functionality on
	 * @return array $sortable, the array of columns that can be sorted by the user
	 */
	public function get_sortable_columns() {
		return $this->_sort_columns;
	}

	/**
	 * Prepare the table with different parameters, pagination, columns and table elements
	 */
	function prepare_items() {
		global $wpdb, $_wp_column_headers;
		$screen = get_current_screen();

		/* -- Preparing your query -- */
		if ( !isset($this->_table_name) ) return false;
		$tbl = $wpdb->prefix . $this->_table_name;
		$page = @$_GET['page'];
		$s = @$_POST['s'];
		
		// build where clause with search string
		if ( $s && isset($this->_search) ) {
			foreach( $this->_search as $key => $col) {
				if (! isset($where) ) $where = "WHERE (";
				if (! isset($i) ) {
					$i = 1;
					$where .= "$col LIKE '%$s%'";
				} else {
					$where .= " OR $col LIKE '%$s%'";
				}
				if ( count($this->_search) == $i) $where .= ")";
				$i++;
			}
			// use where clause override
			if ( isset($this->_where) && isset($where) ) $where .= " AND ({$this->_where})";
		}
		if (! isset($where) && isset($this->_where) ) $where = "WHERE ({$this->_where})";
		
		// the query
		if ( isset($this->_query) ) $query = $this->_query;
		else $query = "SELECT * FROM $tbl ".(isset($where)?$where:"");

		/* -- Ordering parameters -- */
	    //Parameters that are going to be used to order the result
	    $orderby = !empty($_GET["orderby"]) ? mysql_real_escape_string($_GET["orderby"]) : '';
	    $order = !empty($_GET["order"]) ? mysql_real_escape_string($_GET["order"]) : 'ASC';
	    if(!empty($orderby) & !empty($order)){ $query.=' ORDER BY '.$orderby.' '.$order; }

		/* -- Pagination parameters -- */
        //Number of elements in your table?
        $totalitems = $wpdb->query($query); //return the total number of affected rows
        //How many to display per page?
        $perpage = 10;
        //Which page is this?
        $paged = !empty($_GET["paged"]) ? mysql_real_escape_string($_GET["paged"]) : '';
        //Page Number
        if(empty($paged) || !is_numeric($paged) || $paged<=0 ){ $paged=1; }
        //How many pages do we have in total?
        $totalpages = ceil($totalitems/$perpage);
        //adjust the query to take pagination into account
	    if(!empty($paged) && !empty($perpage)){
		    $offset=($paged-1)*$perpage;
    		$query.=' LIMIT '.(int)$offset.','.(int)$perpage;
	    }

		/* -- Register the pagination -- */
		$this->set_pagination_args( array(
			"total_items" => $totalitems,
			"total_pages" => $totalpages,
			"per_page" => $perpage,
		) );
		//The pagination links are automatically built according to those parameters

		 /* — Register the Columns — */
		$columns = $this->get_columns();
		//$_wp_column_headers[$screen->id]=$columns;
		$hidden = array('col_id');
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		/* -- Fetch the items -- */
		$this->items = $wpdb->get_results($query);
	}

	/**
	 * Get an associative array ( option_name => option_title ) with the list
	 * of bulk actions available on this table.
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @return array
	 */
	function get_bulk_actions() {
		return $this->_bulk_actions;
	}

	/**
	 * Display the rows of records in the table
	 * @return string, echo the markup of the rows
	 */
	function display_rows() {
		$page = @$_GET['page'];
		$i=0;

		//Get the records registered in the prepare_items method
		$records = $this->items;

		//Get the columns registered in the get_columns and get_sortable_columns methods
		list( $columns, $hidden ) = $this->get_column_info();
		
		//Loop for each record
		if(!empty($records)){foreach($records as $rec){
			$i++;
			//Open the line
	        echo '<tr id="record_'.$rec->id.'" class="'.($i%2?'alternate':'').'">';
			foreach ( $columns as $column_name => $column_display_name ) {

				//Style attributes for each col
				$classExtra = ($column_name=="col_image"?"media-icon":"");
				$class = "class='$column_name column-$column_name $classExtra'";
				$style = "";
				if ( in_array( $column_name, $hidden ) ) $style = ' style="display:none;"';
				$attributes = $class . $style;

				//edit link
				$editlink  = admin_url("admin.php?page=$page&action=view&id=".(int)$rec->user_pending_id);
				
				//pending member info
				$user = new WP_User( $rec->user_pending_id );

				//Display the cell
				switch ( $column_name ) {
					case "cb":	echo '<th scope="row" class="check-column"><input type="checkbox" name="records[]" value="'.$rec->id.'" /></th>'; break;
					case "col_first_name": echo '<td '.$attributes.'>'.$rec->first_name.'</td>'; break;
					case "col_last_name": echo '<td '.$attributes.'>'.$rec->last_name.'<div class="row-actions"><span class="view"><a href="'.$editlink.'" title="View">View Details</a></span></div></td>'; break;
					case "col_email": echo '<td '.$attributes.'>'.$rec->user_email.'</td>'; break;
					case "col_member_type": echo '<td '.$attributes.'>'.implode(", ",$user->roles).'</td>'; break;
					case "col_registration_date": echo '<td '.$attributes.'>'.$rec->user_registered.'</td>'; break;
				}
			}

			//Close the line
			echo'</tr>';
		}}
	}
}
