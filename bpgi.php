<?php
/**
 * Plugin Name: BuddyPress Groups Import
 * Plugin URI: http://wordpress.org/plugins/buddypress-groups-import/
 * Description: Import BuddyPress groups from CSV file.
 * Version: 0.2
 * Text Domain: bpgi
 * Domain Path: /languages
 * Author: Türker YILDIRIM
 * Author URI: http://turkeryildirim.com/
 * License: GPLv3
 */


# load translated strings
function bpgi_textdomain() {
    load_plugin_textdomain( 'bpgi', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'bpgi_textdomain' );

# check required wordpress plugins
register_activation_hook( __FILE__, 'bpgi_install' );
function bpgi_install() {
    global $bp;

    # Check whether BP is active and whether Groups component is loaded, and throw error if not
    if(!(function_exists('BuddyPress') || is_a($bp,'BuddyPress')) || !bp_is_active('groups')) {
        __( 'BuddyPress is not installed or the Groups component is not activated. Cannot continue install.', 'bpgi' );
        exit;
    }
}

# register admin menu
add_action('admin_menu', 'bpgi_admin_menu_register');
function bpgi_admin_menu_register() {
	add_submenu_page(
        'tools.php',
        __( 'BP Groups Import', 'bpgi' ),
        __( 'BP Groups Import', 'bpgi' ),
		'publish_pages',
		'bp-groups-import',
		'bpgi_page_display'
	);
}

# display admin page
function bpgi_page_display() {
    # check user capability
	if ( !current_user_can( 'publish_pages' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'bpgi' ) );
	}

    # pre-setup
    $notice = '';
    $errors = '';

    # start import
    if (!empty($_POST)) {
        global $bp, $wpdb;

        # pre-controls
        if ($_FILES['csv_file']['size'] == 0) $errors[] = __( 'Choose a CSV file', 'bpgi' );
        if ($_FILES['csv_file']['error'] != 0) $errors[] = __( 'Upload error', 'bpgi' );

        # check errors
        if (!empty($errors)) {
            $errors = implode('<br>', $errors);
            $notice = '<div class="error settings-error" id="setting-error"><p><strong>'.$errors.'</strong></p></div>';
        }
        else {
            # extract post values
            extract( $_POST, EXTR_OVERWRITE );

            # get form values and validate
            $parent_group_id = abs($parent_group);
            if ($group_status != 'public' || $group_status != 'private' || $group_status != 'hidden' ) $group_status = 'public';
            if ($group_invite_status != 'mods' || $group_invite_status != 'admins' || $group_invite_status != 'members') $group_invite_status = 'members';
            if (!isset($group_forum)) $group_forum = false;
            if (!isset($group_overwrite)) $group_overwrite = false;

            # load CSV file
            if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                # read 1000 lines per run
                $group_count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    # get details from csv file
                    $csv_group_parent = trim($data[0]);
                    $csv_group_name = trim($data[1]);
                    $csv_group_description = trim($data[2]);
                    $csv_group_status = trim($data[3]);
                    $csv_group_forum = trim($data[4]);
                    $csv_group_invite_status = trim($data[5]);

                    if ($csv_group_forum == 'yes') $csv_group_forum = 1; else $csv_group_forum = 0;

                    # find parent group ID
                    $parent_group_id = groups_get_id(sanitize_title_with_dashes(esc_attr($csv_group_parent)));

                    # bulk overwrite
                    if ($group_overwrite == 'yes') {
                        $csv_group_parent = $parent_group;
                        $csv_group_status = $group_status;
                        $csv_group_forum = $group_forum;
                        $csv_group_invite_status = $group_invite_status;

                        $parent_group_id = $csv_group_parent;
                    }

                    $group_slug = groups_check_slug(sanitize_title_with_dashes(esc_attr($csv_group_name)));


                    # create group
                    $args = array (
                        'name'          => $csv_group_name,
                        'description'   => $csv_group_description,
                        'slug'          => $group_slug,
                        'status'        => $csv_group_status,
                        'enable_forum'  => $csv_group_forum
                    );
                    $new_group_id = groups_create_group ($args);

                    # group created successfully
                    if (!empty($new_group_id)) {
                        $u = '<a href="'.bp_loggedin_user_domain('/').'" title="'.bp_get_loggedin_user_username().'">'. bp_get_loggedin_user_username().'</a>';
                        $g = '<a href="'.site_url().'/groups/'.$group_slug.'/">'.$csv_group_name.'</a>';

                        # add BP activity
                        bp_activity_add (array(
                          'action'            => sprintf ( __( '%s created the group %s', 'bpgi'), $u, $g),
                          'component'         => 'groups',
                          'type'              => 'created_group',
                          'primary_link'      => bp_loggedin_user_domain('/'),
                          'user_id'           => bp_loggedin_user_id(),
                          'item_id'           => $new_group_id
                        ));

                        # set invite status
                        groups_update_groupmeta( $new_group_id, 'invite_status', $csv_group_invite_status );

                        # create group forum if enabled, requires bbPress
                        if ($csv_group_forum == 1) {
                            bbp_insert_forum( array(
                                'post_parent'    => bbp_get_group_forums_root_id(),
                                'post_title'     => $csv_group_name,
                                'post_content'   => $csv_group_description,
                                'post_status'    => 'publish',
                                'post_type'      => 'forum',
                                'post_author'    => bp_loggedin_user_id(),
                                'comment_status' => 'closed'
                            ));
                        }

                        # set parent group if exist
                        if ($parent_group_id>0 && is_plugin_active('bp-group-hierarchy/index.php')) {
                            $sql = $wpdb->prepare(
                				"UPDATE {$bp->groups->table_name} SET parent_id = %d WHERE id = %d",
                				$parent_group_id,
                				$new_group_id
                			);
                            $wpdb->query($sql);
                        }

                        $group_count++;
                    }
                    else {
                        $errors[] = sprintf( __( 'Cannot create group %s, probably a temporary mysql error', 'bpgi' ), $csv_group_name);
                    }// else

                } // while
                fclose($handle);
            } // if
            else {
                $errors[] = __( 'Cannot open uploaded CSV file, contact your hosting support.', 'bpgi' );
            }

            # check errors
            if (!empty($errors)) {
                $errors = implode('<br>', $errors);
                $notice = '<div class="error settings-error" id="setting-error"><p><strong>'.$errors.'</strong></p></div>';
            }
            else {
                $notice = '<div class="updated settings-error" id="setting-error"><p><strong>'
                        .sprintf ( __( 'Total %d groups are imported.', 'bpgi'), $group_count )
                        .'</strong></p></div>';
            }

        } // else
    } // if

    # get currently created groups
    $get_groups = groups_get_groups( array(
       'populate_extras'   => 'false',
       'order'             => 'ASC',
       'orderby'           => 'name')
    );

    # prepare group selectbox
    $groups = '<select name="parent_group" id="parent_group" class="regular-text code"><option value="0">'. __( 'Root', 'bpgi').'</option>';
    foreach ($get_groups['groups'] as $group) {
        $groups.='<option value="'.$group->id.'">'.esc_html($group->name).'</option>';
    }
    $groups.='</select>';

    # display admin page content
	echo '<div class="wrap">';
		echo '<h2>'. __( 'BuddyPress Groups Import', 'bpgi').'</h2>';

        echo $notice;

        echo '<p>'.__( 'This plugin imports BuddyPress groups with their settings from a CSV file.', 'bpgi');
        echo '<p>'.__( 'It also supports', 'bpgi').' <a href="http://wordpress.org/plugins/bp-group-hierarchy/">'.__( 'BP Group Hierarchy', 'bpgi').'</a></p>';
        echo '<p>'.__( 'Preapare CSV file, select bulk settings if needed and then click import. That is all, enjoy', 'bpgi').'</p>';
        echo '<p><strong>'. __( 'Notes :', 'bpgi').'</strong><br>';
        echo __( '* CSV file structure must match with the sample.', 'bpgi').'<br>';
        echo __( '* Bulk settings will overwrite CSV file settings', 'bpgi').'<br>';
        echo __( '* If you get "Request timeout" or similar timeout message while trying to import large CSV file contact your hosting support or split your files into two or more part.', 'bpgi').'<br>';
        echo __( '* CSV file structure must match with the sample one', 'bpgi').'<br>';
        echo '</p>';

        echo '<form name="form" action="tools.php?page=bp-groups-import" method="post" enctype="multipart/form-data">';
        echo '<h3>'. __( 'Import Groups', 'bpgi').'</h3>';
        echo '<table class="form-table"><tbody>';
	    echo '<tr><th><label for="group_overwrite">'. __( 'Overwrite Settings ?', 'bpgi').'</label></th>';
		echo '<td><input name="group_overwrite" id="group_overwrite" type="checkbox" value="yes" class="code"></td></tr>';
	    echo '<tr><th><label for="parent_group">'. __( 'Parent Group', 'bpgi').'</label></th>';
		echo '<td>'.$groups.'</td></tr>';
	    echo '<tr><th><label for="group_status">'. __( 'Group Status', 'bpgi').'</label></th>';
		echo '<td><select name="group_status" id="group_status" class="regular-text code"><option value="public">'. __( 'Public', 'bpgi').'</option><option value="private">'. __( 'Private', 'bpgi').'</option><option value="hidden">'. __( 'Hidden', 'bpgi').'</option></select></td></tr>';
	    echo '<tr><th><label for="group_status">'. __( 'Group Invite Status', 'bpgi').'</label></th>';
		echo '<td><select name="group_invite_status" id="group_invite_status" class="regular-text code"><option value="members">'. __( 'All Users', 'bpgi').'</option><option value="mods">'. __( 'Mods and Admins', 'bpgi').'</option><option value="admins">'. __( 'Admins', 'bpgi').'</option></select></td></tr>';
	    echo '<tr><th><label for="group_forum">'. __( 'Enable Forum ?', 'bpgi').'</label></th>';
		echo '<td><input name="group_forum" id="group_forum" type="checkbox" value="1" checked class="code"></td></tr>';
        echo '<tr><th><label for="csv_file">'. __( 'Choose CSV File :', 'bpgi').' </label></th>';
		echo '<td><input type="file" id="csv_file" name="csv_file" size="25"></td></tr>';
	    echo '</tbody></table>';
        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="'. __( 'Start Import', 'bpgi').'"></p>';
        wp_nonce_field( 'bp-groups-import' );
        echo '</form><p></p>';

        echo '<p><h3>'. __( 'CSV Sample', 'bpgi').'</h3><code>';
        echo __( '[Parent Group Name],[Group Name],[Group Description],[Group Status],[Group Forum Enabled],[Group Invite Status]', 'bpgi').'<br>';
        echo __( 'Test group, sub group, sub group description, private, yes, mods', 'bpgi').'<br>';
        echo __( ',top level group, description, hidden, no, admins', 'bpgi').'<br>';
        echo __( ',top level group 2, description 2, , ,', 'bpgi').'<br>';
        echo '</code></p>';
	echo '</div>';


}

?>