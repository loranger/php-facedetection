<?php

set_time_limit(0);

include "FaceDetector.php";

$detector = new FaceDetector('lena512color.jpg'); // Load an image and detect faces

//$detector->setPadding(50); /// Define a padding or use the default relative one

//$detector->crop(); // Crop face

$detector->highlight(); // Or highlight face

//$detector->save(); // Save the current modification in the current file (highlight or crop)
//$detector->save('/home/user/export.png'); // Or save in another current file

$detector->display(); // Or display file as jpg
//$detector->display('png'); // Or as png
die();
