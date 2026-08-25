<?php
/**
 * Plugin Name: Snippet Import Tool (TEMPORARY)
 * Description: One-time tool to import snippets-export.json into Simple HTML Snippets. Requires the Simple HTML Snippets plugin to be active. Adds Tools > Import HTML Snippets. Delete this plugin after use.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', function () {
    add_management_page(
        'Import HTML Snippets',
        'Import HTML Snippets',
        'manage_options',
        'shs-import',
        'shs_import_page'
    );
} );

function shs_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }

    if ( ! post_type_exists( 'html_snippet' ) ) {
        echo '<div class="wrap"><h1>Import HTML Snippets</h1>';
        echo '<p><strong>The "html_snippet" post type isn\'t registered.</strong> Make sure the Simple HTML Snippets plugin is installed and active first.</p></div>';
        return;
    }

    echo '<div class="wrap"><h1>Import HTML Snippets</h1>';
    echo '<p>Paste the JSON produced by the export tool below.</p>';
    echo '<form method="post">';
    wp_nonce_field( 'shs_import' );
    echo '<textarea name="shs_json" rows="20" style="width:100%; font-family:monospace;"></textarea>';
    echo '<p><label><input type="checkbox" name="shs_overwrite" value="1"> Overwrite existing snippets with the same ID</label></p>';
    echo '<p><button class="button button-primary" name="shs_import_submit" value="1">Import</button></p>';
    echo '</form>';

    if ( isset( $_POST['shs_import_submit'] ) && check_admin_referer( 'shs_import' ) ) {
        $json = wp_unslash( $_POST['shs_json'] );
        $data = json_decode( $json, true );

        if ( ! is_array( $data ) ) {
            echo '<div class="notice notice-error"><p>Could not parse that as JSON. Check for stray characters and try again.</p></div>';
            echo '</div>';
            return;
        }

        $overwrite = ! empty( $_POST['shs_overwrite'] );
        $created   = 0;
        $updated   = 0;
        $skipped   = 0;

        foreach ( $data as $row ) {
            if ( empty( $row['id'] ) ) {
                continue;
            }
            $id      = sanitize_text_field( $row['id'] );
            $content = isset( $row['content'] ) ? $row['content'] : '';

            $existing = get_page_by_title( $id, OBJECT, 'html_snippet' );

            if ( $existing && ! $overwrite ) {
                $skipped++;
                continue;
            }

            if ( $existing && $overwrite ) {
                update_post_meta( $existing->ID, '_shs_raw_content', $content );
                $updated++;
                continue;
            }

            $post_id = wp_insert_post( array(
                'post_type'   => 'html_snippet',
                'post_title'  => $id,
                'post_status' => 'publish',
            ) );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_shs_raw_content', $content );
                $created++;
            }
        }

        echo '<div class="notice notice-success"><p>Done. Created: ' . (int) $created . ', Updated: ' . (int) $updated . ', Skipped (already existed): ' . (int) $skipped . '.</p></div>';
        echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=html_snippet' ) ) . '">View your imported HTML Snippets</a></p>';
    }

    echo '</div>';
}