<?php
set_time_limit(0);

include "FaceDetector.php";

if ( !function_exists('getParam') ) {
    function getParam($key, $default = false)
    {
        if ( is_array($_POST) && array_key_exists($key, $_POST) ) {
            $default = $_POST[$key];
        }

        return $default;
    }
}

if ( getParam('remote') != '' || (is_array($_FILES) && array_key_exists('local', $_FILES) && is_uploaded_file($_FILES['local']['tmp_name'])) ) {
    define('BYPASS_DEBUG', true);
    if ( is_uploaded_file($_FILES['local']['tmp_name']) ) {
        $tmpfname = $_FILES['local']['tmp_name'];
    } elseif ( trim(getParam('remote')) != '' ) {
        $remote = file_get_contents( trim(getParam('remote')) );
        $tmpfname = tempnam("/tmp", "FACE");

        $handle = fopen($tmpfname, "w");
        fwrite($handle, $remote);
        fclose($handle);
    }

    $detector = new FaceDetector($tmpfname);
    if ( getParam('crop') == 1 ) {
        $detector->crop(40);
    } else {
        $detector->highlight();
    }
    $detector->display();
    die();

}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Face detection</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <link href="http://twitter.github.com/bootstrap/assets/css/bootstrap.css" rel="stylesheet">

    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
  </head>

  <body>

    <div class="container hero-unit">

    <h1>PHP Face Detection</h1>
    <p>
        Pure PHP Face detection, Imagick extension based, no OpenCV
    </p>
    <br />

    <form enctype="multipart/form-data" class="form-inline" action="" method="post">
        <label>Image URL :
            <input type="text" class="span6" name="remote" placeholder="http://www.site.com/image.jpg" />
        </label>
        <span>OR</span>
        <label>Image File :
<?php

$POST_MAX_SIZE = ini_get('post_max_size');
$mul = substr($POST_MAX_SIZE, -1);
$mul = ($mul == 'M' ? 1048576 : ($mul == 'K' ? 1024 : ($mul == 'G' ? 1073741824 : 1)));

?><input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $mul*(int) $POST_MAX_SIZE; ?>" />
            <input type="file" name="local" />
        </label>
        <br /><br />
        <div class="pull-right">
            <label class="checkbox">
                <input type="checkbox" name="crop" value="1"> Crop
            </label>
            &nbsp;
            <button type="submit" class="btn">Detect face</button>
        </div>
    </form>

    </div>

  </body>
</html>
