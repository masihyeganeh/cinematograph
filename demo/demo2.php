<?php

include '../cinematograph.php';

$frames = array(
	1 => '1.png',
	2 => '2.png',
	3 => '3.png',
	4 => '4.png',
	5 => '5.png',
	6 => '6.png',
	7 => '7.png',
	8 => '8.png'
);

// Initialize Cinemtograph with array of images and base path
$cinematograph = new Cinematograph($frames, 'source_images/');

// Don't save the mask in a file, we need it in a web page
$mask = $cinematograph->makeMask(BASE64_ENCODED_DATA_URI, true);

// Save the image
$cinematograph->makeImage('../result/image.png');

?>
<html>
	<head>
		<title>Cinematograph Demo2</title>
		<style type="text/css">
		.container {
			position: relative;
			display: inline-block;
		}
		.mask {
			width: 100%;
			height: 100%;
			position: absolute;
			background: url(<?=$mask?>);
			left: 0;
			top: 0;
		}
		</style>
	</head>
	<body>
		<p>With a little Javascript or CSS you can have the motion in web</p>
		<div class="container">
			<img src="result/image.png">
			<div class="mask" style="background-position-x: 0px;"></div>
		</div>
		<script type="text/javascript">
		var mask = document.getElementsByClassName('mask')[0];
		var position = 0;
		setInterval(function() {
			if (position++ > 1000) position = 0;
			mask.style.backgroundPositionX = position + 'px';
		}, 100);
		</script>
	</body>
</html>