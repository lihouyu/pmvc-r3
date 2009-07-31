<?php
define('KARAVIDEO', 1);

include_once('secret.php');

include_once('lib/my_db.php');
include_once('lib/active_object.php');

include_once('include/video_category.php');
include_once('include/video.php');
include_once('include/video_comment.php');

$db =& MyDB::get_instance();
$db->close();
