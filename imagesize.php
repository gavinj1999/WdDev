sizes
<?php

$dir ='/home/www/mage2/pub/media/catalog/product/*.jpg';

$images = glob( $dir );
echo('image, width, height');
foreach( $images as $image ):

    list($width, $height, $type, $attr) = getimagesize($image);

    echo($image.','.$width.','.$height.'<br>');

endforeach;








?>