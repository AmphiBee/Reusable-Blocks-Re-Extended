<?php
/**
* Plugin Name:    Reusable Blocks Extended - Patterns
* Plugin URI:     https://amphibee.fr/
* Description:    Extends JB Audras Gutenberg Reusable Blocks plugin feature adding Pattern block management.
* Version:        1.0
* Author:         AmphiBee
* Author URI:     https://amphibee.fr/
* License:        GPL-2.0+
* License URI:    http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:    reusable-blocks-patterns
*/

add_action( 'init', 'reblex_init_plugin', 20 );

/**
 * Init plugin
 */
function reblex_init_plugin() {
	if ( ! reblex_is_reblex_available() || ! reblex_is_patterns_available() ) {
		add_action( 'admin_notices', 'reblex_admin_notice' );
		return;
	}
	add_action( 'save_post', 'reblex_pattern_save_meta' );
	add_action( 'add_meta_boxes', 'reblex_register_pattern_meta_box' );
	add_action( 'admin_enqueue_scripts', 'reblex_enqueue_assets' );

    add_action( 'plugins_loaded', 'reblex_load_plugin_textdomain' );
}

function reblex_load_plugin_textdomain() {
    load_plugin_textdomain( 'reusable-blocks-patterns', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

}

/**
 * Admin notice if requirements are not met
 */
function reblex_admin_notice() {

	$message = array();

	if ( ! reblex_is_patterns_available() ) {
		$message[] = __( 'Patterns are not available in you Gutenberg version. You need to grab the latest version of <a target="_blank" href="https://wordpress.org/plugins/gutenberg/">Gutenberg</a>.', 'reusable-blocks-patterns' );
	}
	if ( ! reblex_is_reblex_available() ) {
		$message[] = __( 'You need to install and activate the awesome <a target="_blank" href="https://wordpress.org/plugins/reusable-blocks-extended/">Reusable Blocks Extended</a> in order to use Reusable Blocks Extended - Patterns', 'reusable-blocks-patterns' );
	}

	$message_output  = '<p><strong>Reusable Blocks Extended - Patterns</strong></p>';
	$message_output .= implode( '<br>', $message );

	echo "<div class=\"notice notice-warning is-dismissible\"><p>{$message_output}</p></div>";
}


/**
 * Tell if Reusable Blocks Extended is installed
 */
function reblex_is_reblex_available() {
	return function_exists( 'reblex_reusable_menu_display' );
}

/**
 * Tell if pattern are available inside Gutenberg
 */
function reblex_is_patterns_available() {
	return function_exists( 'register_block_pattern' );
}

add_filter( 'rest_wp_block_query', 'reblex_rest_wp_block_query', 99, 2 );

/**
 * Filtering the WP Block rest api request
 * @param $args : rest query arguments
 * @return mixed
 */
function reblex_rest_wp_block_query($args ) {

	if ( $args['post_type'] === 'wp_block' ) {

		// create meta query arg if not set
		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
			);
		}

		// only blocks not registered as hidden
		$args['meta_query'][] = array(
            'key'     => '_reblex_pattern-hide-wp_block',
            'compare' => 'NOT EXISTS',
        );
	}

	return $args;
}

/**
 * Enqueue assets
 */
function reblex_enqueue_assets() {
	wp_enqueue_style( 'slider', plugin_dir_url( __FILE__ ) . 'assets/css/reblex.css', array(), '1.0' );
}

add_action( 'init', 'reblex_register_patterns', 30 );

/**
 * Register patterns
 */
function reblex_register_patterns() {

	// get all the wp_block with pattern mode
	$args = array(
		'post_type'  => 'wp_block',
		'status'     => 'publish',
		'meta_key'   => '_reblex_pattern',
		'meta_value' => 'on',
	);

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) {
		foreach ( $query->posts as $pattern ) {
			$pattern_name = $pattern->post_title . '-' . $pattern->ID;
			register_block_pattern(
				"reblex/{$pattern_name}",
				array(
					'title'   => $pattern->post_title,
					'content' => $pattern->post_content,
				)
			);
		}
	}
}


/**
 * Add pattern metabox
 */
function reblex_register_pattern_meta_box() {
	add_meta_box(
		'reblex-save-pattern',
		__( 'Pattern block', 'reusable-blocks-patterns' ),
		'reblex_pattern_manage',
		'wp_block',
		'side'
	);
}

/**
 * Pattern meta box
 * @param $post : current edited post
 */
function reblex_pattern_manage( $post ) {
	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'reblex_pattern_save', 'reblex_pattern_nonce' );

	// Use get_post_meta to retrieve an existing value from the database.
	$value      = get_post_meta( $post->ID, '_reblex_pattern', true );
	$hide_value = get_post_meta( $post->ID, '_reblex_pattern-hide-wp_block', true );

	$checked      = $value ? 'checked="checked"' : '';
	$hide_checked = $hide_value ? 'checked="checked"' : '';

	// Display the form, using the current value.
	?>
	<div class="inside">
		<div class="pattern-enable">
			<input type="checkbox" id="reblex-pattern" name="_reblex_pattern"  <?php echo $checked; ?>/>
			<label for="reblex-pattern">
				<?php _e( 'Use as pattern block', 'reusable-blocks-patterns' ); ?>
			</label>
			<div class="reblex-pattern-conditional">
				<div class="reblex-pattern-hide">
					<strong><?php _e( 'Pattern block settings', 'reusable-blocks-patterns' ); ?></strong></em>
				</div>
				<ul class="post-types">
					<li>
						<input type="checkbox" id="reblex-pattern-hide" name="_reblex_pattern-hide-wp_block"  <?php echo $hide_checked; ?>/>
						<label for="reblex-pattern-hide">
							<?php _e( 'Hide block from the Reusable block list', 'reusable-blocks-patterns' ); ?>
						</label>
					</li>
				</ul>
			</div>
		</div>
	</div>
	<?php
}


/**
 * Save pattern meta box content.
 *
 * @param int $post_id Post ID
 */
function reblex_pattern_save_meta( $post_id ) {
	// Save logic goes here. Don't forget to include nonce checks!
	// Add nonce for security and authentication.
	$nonce_name   = isset( $_POST['reblex_pattern_nonce'] ) ? $_POST['reblex_pattern_nonce'] : '';
	$nonce_action = 'reblex_pattern_save';

	// Security check
	if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$enable_pattern = $_POST['_reblex_pattern'];
	$hide_block     = $_POST['_reblex_pattern-hide-wp_block'];

	update_post_meta( $post_id, '_reblex_pattern', $enable_pattern );

	if ( $hide_block ) {
		update_post_meta( $post_id, '_reblex_pattern-hide-wp_block', $_POST['_reblex_pattern-hide-wp_block'] );
	} else {
		delete_post_meta( $post_id, '_reblex_pattern-hide-wp_block' );
	}
}
