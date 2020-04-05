<?php
/**
* Plugin Name:    Reusable Blocks Re-Extended
* Plugin URI:     https://amphibee.fr/
* Description:    Extends JB Audras Gutenberg Reusable Blocks plugin feature adding Pattern block management.
* Version:         0.5.1
* Author:         AmphiBee
* Author URI:     https://amphibee.fr/
* License:        GPL-2.0+
* License URI:    http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:    reusable-blocks-extended
*/

add_action( 'init', 'reblex_init_plugin', 20 );

function reblex_init_plugin() {
	if ( ! function_exists( 'reblex_reusable_menu_display' ) ) {
		return;
	}
    add_action('save_post','reblex_pattern_save_meta');
    add_action( 'add_meta_boxes', 'reblex_register_pattern_meta_box' );
}

add_action( 'admin_enqueue_scripts', 'reblex_enqueue_assets' );

function reblex_enqueue_assets() {
    wp_enqueue_style( 'slider', plugin_dir_url( __FILE__ ) . 'assets/css/reblex.css', array(), '1.0' );
}

add_action( 'init', 'reblex_register_patterns', 30 );

function reblex_register_patterns() {
    $args = array(
            'post_type' => 'wp_block',
            'status' => 'publish',
            'meta_key' => '_reblex_pattern',
            'meta_value' => 'on'
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        foreach( $query->posts as $pattern ) {
            $pattern_name = $pattern->post_title . '-' . $pattern->ID;
            register_pattern(
                "reblex/{$pattern_name}",
                array(
                    'title'   => $pattern->post_title,
                    'content' => $pattern->post_content,
                )
            );
        }
    }
}


function reblex_register_pattern_meta_box() {
    add_meta_box(
        'reblex-save-pattern',
        __( 'Pattern block', 'reusable-blocks-reextended' ),
        'reblex_pattern_manage',
        'wp_block',
        'side'
    );
}

function reblex_pattern_manage( $post ) {
    // Add an nonce field so we can check for it later.
    wp_nonce_field( 'reblex_pattern_save', 'reblex_pattern_nonce' );


    // Use get_post_meta to retrieve an existing value from the database.
    $value = get_post_meta( $post->ID, '_reblex_pattern', true );
    $cpt_values = get_post_meta( $post->ID, '_reblex_pattern-cpt', true );

    if ( ! is_array( $cpt_values ) ) {
        $cpt_values = array();
    }

    $checked = $value ? 'checked="checked"' : '';

    $post_types = get_post_types(array(), 'objects');
    $blacklist = array( 'wp_block', 'attachment', 'nav_menu_item', 'wp_area' );

    // Display the form, using the current value.
    ?>
    <div class="inside">
        <div class="pattern-enable">
            <input type="checkbox" id="reblex-pattern" name="_reblex_pattern"  <?php echo $checked; ?>/>
            <label for="reblex-pattern">
                <?php _e( 'Use as pattern block', 'reusable-blocks-reextended' ); ?>
            </label>
            <div class="reblex-pattern-conditional">
                <div class="reblex-pattern-ctp-title">
                    <strong><?php _e( 'Enable by post type', 'reusable-blocks-reextended' ); ?></strong> <em>(<?php _e( 'Default : all', 'reusable-blocks-reextended' ); ?>)</em>
                </div>
                <ul class="post-types">
                    <?php foreach ($post_types as $post_type=>$parameters): ?>
                        <?php if (
                            $parameters->show_in_rest
                            && current_user_can( $parameters->cap->edit_posts )
                            && ! in_array( $post_type, $blacklist )
                        ): ?>
                            <?php
                                $cpt_checked = in_array( $post_type, $cpt_values ) ? 'checked="checked"' : '';
                            ?>
                            <li>
                                <input type="checkbox" id="reblex-pattern" value="<?php echo $post_type; ?>" name="_reblex_pattern-cpt[]"  <?php echo $cpt_checked; ?>/>
                                <label for="reblex-pattern">
                                    <?php echo $parameters->label; ?>
                                </label>
                            </li>
                        <?php endif;?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php
}


/**
 * Save meta box content.
 *
 * @param int $post_id Post ID
 */
function reblex_pattern_save_meta( $post_id ) {
    // Save logic goes here. Don't forget to include nonce checks!
    // Add nonce for security and authentication.
    $nonce_name   = isset( $_POST['reblex_pattern_nonce'] ) ? $_POST['reblex_pattern_nonce'] : '';
    $nonce_action = 'reblex_pattern_save';

    // Check if nonce is valid.
    if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
        return;
    }

    // Check if user has permissions to save data.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Check if not an autosave.
    if ( wp_is_post_autosave( $post_id ) ) {
        return;
    }

    // Check if not a revision.
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    update_post_meta( $post_id, '_reblex_pattern', $_POST['_reblex_pattern'] );
    update_post_meta( $post_id, '_reblex_pattern-cpt', $_POST['_reblex_pattern-cpt'] );
}
