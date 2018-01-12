<?php
define( 'USER_CATEGORY_NAME', 'user_category' );
define( 'USER_CATEGORY_META_KEY', '_user_category' );

add_action( 'init', 'nopio_register_user_category_taxonomy' );

function nopio_register_user_category_taxonomy() {
	register_taxonomy(
		USER_CATEGORY_NAME, //taxonomy name
		'user', //object for which the taxonomy is created
		array( //taxonomy details
			'public' => true,
			'labels' => array(
				'name' => 'User Categories',
				'singular_name' => 'User Category',
				'menu_name' => 'User Categories',
				'search_items' => 'Search User Category',
				'popular_items' => 'Popular User Categories',
				'all_items' => 'All User Categories',
				'edit_item' => 'Edit User Category',
				'update_item' => 'Update User Category',
				'add_new_item' => 'Add New User Category',
				'new_item_name' => 'New User Category Name',
			),
			'update_count_callback' => function() {
				return; //important
			}
		)
	);
}

add_action( 'admin_menu', 'nopio_add_user_categories_admin_page' );

function nopio_add_user_categories_admin_page() {
	$taxonomy = get_taxonomy( USER_CATEGORY_NAME );
	add_users_page(
		esc_attr( $taxonomy->labels->menu_name ),//page title
		esc_attr( $taxonomy->labels->menu_name ),//menu title
		$taxonomy->cap->manage_terms,//capability
		'edit-tags.php?taxonomy=' . $taxonomy->name//menu slug
	);
}

add_filter( 'submenu_file', 'nopio_set_user_category_submenu_active' );

function nopio_set_user_category_submenu_active( $submenu_file ) {
	global $parent_file;
	if( 'edit-tags.php?taxonomy=' . USER_CATEGORY_NAME == $submenu_file ) {
		$parent_file = 'users.php';
	}
	return $submenu_file;
}

add_action( 'show_user_profile', 'nopio_admin_user_profile_category_select' );
add_action( 'edit_user_profile', 'nopio_admin_user_profile_category_select' );

function nopio_admin_user_profile_category_select( $user ) {
	$taxonomy = get_taxonomy( USER_CATEGORY_NAME );
	
	if ( !user_can( $user, 'author' ) ) {
		return;
	}
	?>
	<table class="form-table">
		<tr>
			<th>
				<label for="<?php echo USER_CATEGORY_META_KEY ?>">User Category</label>
			</th>
			<td>
				<?php
					$user_category_terms = get_terms( array(
						'taxonomy' => USER_CATEGORY_NAME,
						'hide_empty' => 0
					) );
					
					$select_options = array();
					
					foreach ( $user_category_terms as $term ) {
						$select_options[$term->term_id] = $term->name;
					}
					
					$meta_values = get_user_meta( $user->ID, USER_CATEGORY_META_KEY, true );
					
					echo nopio_custom_form_select(
						USER_CATEGORY_META_KEY,
						$meta_values,
						$select_options,
						'',
						array( 'multiple' =>'multiple' )
					);
				?>
			</td>
		</tr>
	</table>
	<?php
}

function nopio_custom_form_select( $name, $value, $options, $default_var ='', $html_params = array() ) {
	if( empty( $options ) ) {
		$options = array( '' => '---choose---');
	}

	$html_params_string = '';
	
	if( !empty( $html_params ) ) {
		if ( array_key_exists( 'multiple', $html_params ) ) {
			$name .= '[]';
		}
		foreach( $html_params as $html_params_key => $html_params_value ) {
			$html_params_string .= " {$html_params_key}='{$html_params_value}'";
		}
	}

	echo "<select name='{$name}'{$html_params_string}>";
	
	foreach( $options as $options_value => $options_label ) {
		if( ( is_array( $value ) && in_array( $options_value, $value ) )
			|| $options_value == $value ) {
			$selected = " selected='selected'";
		} else {
			$selected = '';
		}
		if( empty( $value ) && !empty( $default_var ) && $options_value == $default_var ) {
			$selected = " selected='selected'";
		}
		echo "<option value='{$options_value}'{$selected}>{$options_label}</option>";
	}

	echo "</select>";
}

add_action( 'personal_options_update', 'nopio_admin_save_user_categories' );
add_action( 'edit_user_profile_update', 'nopio_admin_save_user_categories' );


function nopio_admin_save_user_categories( $user_id ) {
	$tax = get_taxonomy( USER_CATEGORY_NAME );
	$user = get_userdata( $user_id );

	if ( !user_can( $user, 'author' ) ) {
		return false;
	}
	
	$new_categories_ids = $_POST[USER_CATEGORY_META_KEY];
	$user_meta = get_user_meta( $user_id, USER_CATEGORY_META_KEY, true );
	$previous_categories_ids = array();
	
	if( !empty( $user_meta ) ) {
		$previous_categories_ids = (array)$user_meta;
	}

	if( ( current_user_can( 'administrator' ) && $_POST['role'] != 'author' ) ) {
		delete_user_meta( $user_id, USER_CATEGORY_META_KEY );
		nopio_update_users_categories_count( $previous_categories_ids, array() );
	} else {
		update_user_meta( $user_id, USER_CATEGORY_META_KEY, $new_categories_ids );
		nopio_update_users_categories_count( $previous_categories_ids, $new_categories_ids );
	}
}

function nopio_update_users_categories_count( $previous_terms_ids, $new_terms_ids ) {
	global $wpdb;

	$terms_ids = array_unique( array_merge( (array)$previous_terms_ids, (array)$new_terms_ids ) );
	
	if( count( $terms_ids ) < 1 ) { return; }
	
	foreach ( $terms_ids as $term_id ) {
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value LIKE %s",
				USER_CATEGORY_META_KEY,
				'%"' . $term_id . '"%'
			)
		);
		$wpdb->update( $wpdb->term_taxonomy, array( 'count' => $count ), array( 'term_taxonomy_id' => $term_id ) );
	}
}

function nopio_show_authors_in_categories() {
	$categories = get_terms(array(
		'taxonomy' => USER_CATEGORY_NAME,
		'hide_empty' => true
	));
	
	echo '<ul>';
	foreach( $categories as $category ) {
		echo '<li>';
		echo $category->name;
		echo " (#{$category->count})";
			$args = array( 
				'role' => 'Author', 
				'meta_key'				=> USER_CATEGORY_META_KEY,
				'meta_value'			=> '"' . $category->term_id . '"',
				'meta_compare'		=> 'LIKE'
			);

			$authors = new WP_User_Query( $args );

			echo '<ul>';
				foreach( $authors->results as $author ) {
					echo '<li>';
						echo $author->display_name;
					echo '</li>';
				}
			echo '</ul>';
		
		echo '</li>';
	}
	echo '</ul>';
}
