<?php
/*
Plugin Name:    Context ACF User Avatar 
Description:    Use ACF field instead of Gravatar for user avatar, and neatly store those images in folders
Text-Domain:    cwp-acf-avatar
Version:        1.1
Author:         Robert Andrews
Author URI:     http://www.robertandrews.co.uk
License:        GPL v2 or later
License URI:    https://www.gnu.org/licenses/gpl-2.0.html
*/


// Settings
$acf_field_name = 'avatar';                 // name of ACF image field storing User avatars
$acf_field_key  = 'field_6140a6da30a17';    // key of ACF image field storing User avatars
$avatars_folder = '/users/avatars';         // wp-content/uploads/{sub-folder}


/**
 * ==============================================================================
 *              FILTER USER AVATAR UPLOADS TO SPECIFIC FOLDER
 *  cs. https://support.advancedcustomfields.com/forums/topic/change-file-upload-path-for-one-specific-field/
 * ==============================================================================
 */

// Pre-filter the upload of User Image field "Avatar", key field_6140a6da30a17
add_filter('acf/upload_prefilter/key=' . $acf_field_key, 'prefilter_avatar_upload');
function prefilter_avatar_upload($errors)
{
    // This one added
    add_filter('wp_handle_upload_prefilter', 'avatar_upload_rename');
    // in this filter we add a WP filter that alters the upload path
    add_filter('upload_dir', 'modify_avatar_upload_dir');
    return $errors;
}

// (Old) second filter
function modify_avatar_upload_dir($uploads_avatars)
{
    // here is where we later the path
    $uploads_avatars['path'] = $uploads_avatars['basedir'] . $avatars_folder;
    $uploads_avatars['url'] = $uploads_avatars['baseurl'] . $avatars_folder;
    // $uploads_avatars['subdir'] = 'avatars';
    return $uploads_avatars;
}

/*
     function avatar_upload_rename( $file ) {
         $file['name'] = 'everything-is-awesome-' . $file['name'];
         return $file;
     }
     */




/**
 * ==============================================================================
 *                              NOTICE
 * ==============================================================================
 */

// TODO: Tidy up custom_user_profile_fields()
// 0. WHEN EDIT-USER.PHP LOADS, CAPTURE USER_ID
function custom_user_profile_fields($profileuser)
{
    $myuserid = $_GET['user_id'];
    // echo '<h1>ZSSSDDDHHoo We are Setting $myuserid to ' . $myuserid . '</h1>';
    // $screen = get_current_screen();
    // print_r($screen);
}
// add_action( 'show_user_profile', 'custom_user_profile_fields' );
// add_action( 'edit_user_profile', 'custom_user_profile_fields' );




/**
 * ==============================================================================
 *             RENAME UPLOADED USER AVATAR IMAGE FILE WITH USERNAME
 *    When an image is uploaded to Edit User form through an ACF field (field_6140a6da30a17),
 *    rename file with the username of said user.
 * ==============================================================================
 */

// 1. PASS USER_ID FROM USER-EDIT.PHP TO MEDIA UPLOADER, TO GET USERNAME FOR 
// cf. https://support.advancedcustomfields.com/forums/topic/force-an-image-file-upload-to-a-particular-directory/
// cf. https://wordpress.stackexchange.com/questions/395730/how-to-get-id-of-edit-user-page-during-wp-handle-upload-prefilter-whilst-in-med/395764?noredirect=1#comment577035_395764
add_filter('plupload_default_params', function ($params) {
    if (!function_exists('get_current_screen')) {
        return $params;
    }
    $current_screen = get_current_screen();
    if ($current_screen->id == 'user-edit') {
        $params['user_id'] = $_GET['user_id'];
    } elseif ($current_screen->id == 'profile') {
        $params['user_id'] = get_current_user_id();
    }
    return $params;
});

// 2. ON UPLOAD, DO THE RENAME
// Filter, cf. https://wordpress.stackexchange.com/questions/168790/how-to-get-profile-user-id-when-uploading-image-via-media-uploader-on-profile-pa
// p1: filter, p2: function to execute, p3: priority eg 10, p4: number of arguments eg 2
add_filter('wp_handle_upload_prefilter', 'user_avatar_rename');
function user_avatar_rename($file)
{
    // Working with $POST contents of AJAX Media uploader
    $theuserid = $_POST['user_id'];         // Passed from user-edit.php via plupload_default_params function
    $acffield  = $_POST['_acfuploader'];    // ACF field key, inherent in $_POST
    // If user_id was present AND ACF field is for avatar Image
    if (($theuserid) && ($acffield == $acf_field_key)) {
        // Get ID's username and rename file accordingly, cf. https://stackoverflow.com/a/3261107/1375163
        $user = get_userdata($theuserid);
        $info = pathinfo($file['name']);
        $ext  = empty($info['extension']) ? '' : '.' . $info['extension'];
        $name = basename($file['name'], $ext);
        $file['name'] = $user->user_login . $ext;

        //
        add_action('add_attachment', 'my_set_image_meta_upon_image_upload');

        // Carry on
        return $file;
        // Else, just use original filename
    } else {
        return $file;
    }
}


/**
 * ==============================================================================
 *             ALSO SET IMAGE TITLE & ALT TO USER/POST NAME
 *    When an image is uploaded, use the attached Post (User) edtails
 *    to set Title, Alt fields etc.
 *    cf. https://wordpress.stackexchange.com/a/310689/39300
 *    cf. https://support.advancedcustomfields.com/forums/topic/force-an-image-file-upload-to-a-particular-directory/page/2/
 * ==============================================================================
 */

// https://wordpress.stackexchange.com/a/310689/39300
function my_set_image_meta_upon_image_upload($post_ID)
{

    // "the first thing that your action should do is to remove
    // itself so that it does not run again."
    remove_filter('add_attachment', 'your_function_name_here');

    // Check if uploaded file is an image, else do nothing

    if (wp_attachment_is_image($post_ID)) {

        $my_image_title = get_post($post_ID)->post_title;

        // Added by Robert Andrews
        // Get user name to use in image details
        $user = get_user_by('slug', $my_image_title);
        $user_name = $user->display_name;
        $my_image_title = $user_name;

        // Sanitize the title:  remove hyphens, underscores & extra spaces:
        // $my_image_title = preg_replace( '%[-_]+%', ' ',  $my_image_title );

        // Sanitize the title:  capitalize first letter of every word (other letters lower case):
        // $my_image_title = ucwords( strtolower( $my_image_title ) );

        // Create an array with the image meta (Title, Caption, Description) to be updated
        // Note:  comment out the Excerpt/Caption or Content/Description lines if not needed
        $my_image_meta = array(
            'ID'        => $post_ID,            // Specify the image (ID) to be updated
            'post_title'    => $my_image_title,     // Set image Title to sanitized title
            // 'post_excerpt'  => $my_image_title,     // Set image Caption (Excerpt) to sanitized title
            // 'post_content'  => $my_image_title,     // Set image Description (Content) to sanitized title
        );

        // Set the image Alt-Text
        update_post_meta($post_ID, '_wp_attachment_image_alt', 'Photo of ' . $my_image_title);

        // Set the image meta (e.g. Title, Excerpt, Content)
        wp_update_post($my_image_meta);
    }
}






/**
 * ==============================================================================
 *                      BYPASS GRAVATAR FOR AVATAR
 * ==============================================================================
 */

/**
 * Use ACF image field as avatar
 * @author Mike Hemberger
 * @link http://thestizmedia.com/acf-pro-simple-local-avatars/
 * @uses ACF Pro image field (tested return value set as Array )
 */

add_filter('get_avatar', 'tsm_acf_profile_avatar', 10, 6);
function tsm_acf_profile_avatar($avatar, $id_or_email, $size, $default, $alt, $args)
{
    // cf. https://wordpress.stackexchange.com/questions/287395/how-to-restore-args-for-get-avatar-custom-class
    // This function restores $args capability to pass custom class through get_avatar, by increasing accepted_args to 6, adding $args here and, below, casting and imploding class names in img mark-up

    $user = null; // This added by Robert Andrews to overcome "Notice: Undefined variable" for $user

    // Get user by id or email (get_avatar needs to get a user by either id or email, so account for both)
    if (is_numeric($id_or_email)) {

        $id   = (int) $id_or_email;
        $user = get_user_by('id', $id);
    } elseif (is_object($id_or_email)) {

        if (!empty($id_or_email->user_id)) {
            $id   = (int) $id_or_email->user_id;
            $user = get_user_by('id', $id);
        }
    } else {
        $user = get_user_by('email', $id_or_email);
    }

    if (!$user) {
        return $avatar;
    }

    // Get the user id
    $user_id = $user->ID;

    // Get the file id
    global $acf_field_name;
    $image_id = get_user_meta($user_id, $acf_field_name, true); // CHANGE TO YOUR FIELD NAME

    // Bail if we don't have a local avatar
    if (!$image_id) {
        return $avatar;
    }

    // Get the file size
    $image_url  = wp_get_attachment_image_src($image_id, 'thumbnail'); // Set image size by name
    // Get the file url
    $avatar_url = $image_url[0];

    $class = (array) $args['class'];

    // Get the img markup

    /* Run through Cloudinary
         cf. http://cloudinary.com/documentation/image_transformations#modify_image_shape_and_style
         crop fill, gravity face, enhance sharpen, 300 wide and tall only to get square image */
    $avatar = '<img alt="' . $alt . '" src="' . $avatar_url . '" class="' . implode(' ', $class) . ' img-fluid avatar avatar-' . $size . '" height="' . $size . '" width="' . $size . '"/>';

    // Return our new avatar
    return $avatar;
}

?>