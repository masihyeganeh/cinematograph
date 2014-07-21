<?php

include '../cinematograph.php';

// Initialize Cinemtograph with just a directory path
$cinematograph = new Cinematograph('./source_images/');

// Save the mask in a file, we need to print it
$cinematograph->makeMask('result/mask.png');

// Save the image
$cinematograph->makeImage('result/image.png');

?>
<html>
	<head>
		<title>Cinematograph Demo1</title>
	</head>
	<body>
		<p>Print these two images and move the stripped mask over the other image to see the motion.</p>
		<p>Ummm... you know... don't print the mask on a paper. it should be on a transparent surface.</p><br><br>
		<img src="result/image.png"><br>The image<br><br><br>
		<img src="result/mask.png"><br>The mask
	</body>
</html>