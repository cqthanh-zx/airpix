<?php
require_once('../../../../wp-load.php');
require_once( ABSPATH . 'wp-admin/includes/media.php' );
define('STATUS_SUCCESS','SUCCESS');
define('STATUS_ERROR', 'ERROR');
try {

    // Undefined | Multiple Files | $_FILES Corruption Attack
    // If this request falls under any of them, treat it invalid.
    $result = array();
    $result['status'] = STATUS_ERROR;
    $result['message'] = 'Something went wrong!';
    if (
        !isset($_FILES['upfile']['error']) ||
        is_array($_FILES['upfile']['error'])
    ) {
//        throw new RuntimeException('Invalid parameters.');
        $result['message'] = 'Invalid parameters!';
        echo json_encode($result);
        die();
    }

    // Check $_FILES['upfile']['error'] value.
    switch ($_FILES['upfile']['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            $result['message'] = 'No file sent!';
            echo json_encode($result);
            die();
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
                $result['message'] = 'Exceeded filesize limit!';
                echo json_encode($result);
                die();
        default:
                $result['message'] = 'Unknown errors!';
                echo json_encode($result);
                die();
    }

    // You should also check filesize here.
    //200MB
    if ($_FILES['upfile']['size'] > 200000000) {
            $result['message'] = 'Exceeded filesize limit!';
            echo json_encode($result);
            die();
    }

    // DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
    // Check MIME Type by yourself.

    $path = $_FILES['upfile']['name'];
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    if (false === $ext = array_search(
            $ext,
            array(
                'mp4' => 'mp4'
            ),
            true
        )) {
        $result['message'] = $ext;
        echo json_encode($result);
        die();
    }

    // You should name it uniquely.
    // DO NOT USE $_FILES['upfile']['name'] WITHOUT ANY VALIDATION !!
    // On this example, obtain safe unique name from its binary data.

    $uploads = wp_upload_dir();
    $baseDir = $uploads ['basedir'];
    $baseDir = str_replace ( "\\", "/", $baseDir );
    $pathToVideosFolder = $baseDir . '/uploaded-videos';
    $hashedName = sha1_file($_FILES['upfile']['tmp_name']).''.time();
    if (!move_uploaded_file($_FILES['upfile']['tmp_name'],
        sprintf($pathToVideosFolder.'/%s.%s', $hashedName, $ext)
    )) {
        $result['message'] = 'Failed to move uploaded file!';
        echo json_encode($result);
        die();
    }


    $thumbnail = '';
    if(isset($_POST) && isset($_POST['thumbnail'])){

        $data = $_POST['thumbnail'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            if (!in_array($type, array('jpg', 'jpeg', 'gif', 'png'))) {
                $result['message'] = 'Invalid image type!';
                echo json_encode($result);
                die();
            }

            $data = base64_decode($data);

            if ($data === false) {
                $result['message'] = 'Base64_decode failed!';
                echo json_encode($result);
                die();
            }
        } else {
            $result['message'] = 'Did not match data URI with image data!';
            echo json_encode($result);
            die();
        }

        $thumbnail = $hashedName.'.'.$type;

        file_put_contents($pathToVideosFolder.'/'.$thumbnail, $data);

    }

    $description = '';
    $title = '';
    if(isset($_POST) && $_POST['description']){
        $description = trim(htmlspecialchars($_POST['description']));
    }
    
    if(isset($_POST) && $_POST['title']){
        $title = trim(htmlspecialchars($_POST['title']));
    }
    global $wpdb;
    $created_date = $updated_date = current_time ( 'Y-m-d h:i:s' );
    $current_user = wp_get_current_user();

    $file_info = pathinfo($path);
    $display_name = $title ? $title : $file_info['filename'];
    $download_name = $hashedName.'.'.$ext;
    $duration = 0;
    $metadata = wp_read_video_metadata($pathToVideosFolder.'/'.$download_name);

    if($metadata) $duration = $metadata['length_formatted'];

    $query = "INSERT INTO " . $wpdb->prefix . "videos
                                		(user_id, display_name, download_name, thumbnail, description, video_format, duration, created_date, updated_date, is_published)
                           				 VALUES ('$current_user->ID','$display_name','$download_name','$thumbnail','$description','$ext','$duration','$created_date','$updated_date', 0)";

    $wpdb->query ( $query );
    $result['status'] = STATUS_SUCCESS;
    $result['message'] = 'Your video has been uploaded successfully!';
    echo json_encode($result);
    die();

} catch (RuntimeException $e) {

    echo $e->getMessage();

}
