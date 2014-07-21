<?php

define('BASE64_ENCODED_DATA_URI', 'base64');
define('DIRECTLY_OUTPUT',             null);


/**
* Cinematograph
*/
class Cinematograph
{
	protected $frameImages;
	protected $frames;
	protected $framesSize;
	protected $basePath;

	/**
	 * Initialize Cinematograph object
	 * @param array|string $frames   Array of image files address (will be sorted by array keys) or String of images directory (will be sorted by file names)
	 * @param string $basePath Base path to images directory (default is empty)
	 * @throws Exception If GD is not installed
	 * @throws InvalidArgumentException If neither images array nor images directory path passed
	 */
	function __construct($frames, $basePath='')
	{
		$this->basePath = rtrim($basePath, '\\/');
		if (strlen($this->basePath))
			$this->basePath .= DIRECTORY_SEPARATOR;

		if (!function_exists('imagecreate'))
			throw new Exception('Cannot Initialize new GD image stream', 1);

		if (is_array($frames))
		{
			foreach ($frames as &$frame) {
				$frame = $this->basePath . $frame;
			}
			ksort($frames, SORT_NATURAL);
			$this->frameImages = $frames;
		}
		elseif (is_string($frames) && strlen($frames))
		{
			$this->frameImages = array();
			$this->getFramesFromDirectory($this->basePath . $frames);
			natsort($this->frameImages);
		}
		else throw new InvalidArgumentException('Neither images array nor images directory path passed', 2);
		// TODO: maybe I can add an option to use imagefromstring if array of files ($_FILES maybe) given

		$this->frameImages = array_values($this->frameImages);
		$this->frames = count($this->frameImages);

		$this->getFrames();
	}

	/**
	 * Frees memory used by GD images
	 */
	function __destruct()
	{
		if (is_array($this->frameImages))
		{
			foreach ($this->frameImages as &$image) {
				imagedestroy($image);
			}
		}
	}

	/**
	 * Sets base path to images
	 * @param string $basePath Base path to images
	 */
	public function setBasePath($basePath='')
	{
		$this->basePath = rtrim($basePath, '\\/');
		if (strlen($this->basePath))
			$this->basePath .= DIRECTORY_SEPARATOR;
	}

	/**
	 * Retrieves image files from directory path passed to constructor
	 * @param  string $path The path passed to constructor
	 * @throws Exception If path does not exist
	 */
	protected function getFramesFromDirectory($path)
	{
		$path = rtrim($path, '\\/'); // TODO: mind security here
		if (!file_exists($path))
			throw new Exception('Path does not exist', 7);

		$dir = dir($path);			

		while (false !== ($entry = $dir->read())) {
			if ($entry == '..' || $entry == '.') continue;
			$this->frameImages[] = $dir->path . DIRECTORY_SEPARATOR . $entry;
		}

		$dir->close();
	}

	/**
	 * Generates GD images from images path
	 * @throws Exception If all images are not in same size or image type is not supported or not exists
	 */
	protected function getFrames()
	{
		$frame = 0;
		foreach ($this->frameImages as &$image) {
			if (!file_exists($image))
				throw new Exception('Image does not exist', 8);
			$info = getimagesize($image);

			if ($frame > 0)
			{
				if ($info[0] != $this->framesSize[0] || $info[1] != $this->framesSize[1])
					throw new Exception('All images should be in same size', 3);
			}
			else
			{
				$this->framesSize = [$info[0], $info[1]];
			}

			switch ($info['mime']) {
				case 'image/jpeg':
				case 'image/jpg':
					$image = imagecreatefromjpeg($image);
					break;

				case 'image/png':
					$image = imagecreatefrompng($image);
					break;

				case 'image/bmp':
				case 'image/vnd.wap.wbmp':
					$image = imagecreatefromwbmp($image);
					break;

				case 'image/webp':
					if (!function_exists('imagecreatefromwebp'))
						throw new Exception('Webp images are not supported by this version of php', 4);
						
					$image = imagecreatefromwebp($image);
					break;
				
				default:
					throw new Exception('Unsupported image type', 5);
			}
			
			$frame++;
		}
	}

	/**
	 * Generates the stripped image to move on the other image to create the illusion of motion
	 * @param  BASE64_ENCODED_DATA_URI|DIRECTLY_OUTPUT|string $path If BASE64_ENCODED_DATA_URI is passed, the raw mask image stream will be outputted directly.
	 *                                                              If DIRECTLY_OUTPUT is passed, image will be encoded as base64 that can be used in html source attribute.
	 *                                                              Else it should be the path to save the mask image
	 * @param  boolean $repeatableOptimized If you are going to use it on web, this will reduce size and load time
	 * @return string       The data uri only if BASE64_ENCODED_DATA_URI is passed as $path
	 * @throws Exception If path does not exist
	 */
	public function makeMask($path, $repeatableOptimized=false)
	{
		$im = call_user_func_array('imagecreate', $this->framesSize);

		$whiteBackground = imagecolorallocate($im, 255, 255, 255);
		$barsColor       = imagecolorallocate($im,   0,   0,   0); // Black bars color
		imagecolortransparent($im, $whiteBackground);              // Make the background transparent

		$height = $this->framesSize[1];
		$width  = $repeatableOptimized ? $this->frames : $this->framesSize[0];
		/// TODO: Width of image must be a multiple of frames count

		for ($x=0; $x < $width; $x++) {
			if ($x % ($this->frames-1)) // adding $this->frames to $x will start mask with bar
				imageline($im, $x, 0, $x, $height, $barsColor);
		}

		if ($repeatableOptimized)
		{
			// It's actually a crop (maybe same as imagecrop but backward compatible)

			$tmpImage = $im;
			$im = imagecreate($width, 1);

			// Need to set background and transparency again
			imagecolorallocate($im, 255, 0, 0);
			imagecolortransparent($im, $whiteBackground);

			imagecopy($im , $tmpImage, 0, 0, 0, 0, $width, 1);
			imagedestroy($tmpImage);
		}

		if (strtolower($path) == 'base64') return $this->imageToDataUri($im);
		$path = $this->basePath . $path;

		if (!file_exists(dirname($path)))
			throw new Exception('Path does not exist', 9);

		imagepng($im, $path);
		imagedestroy($im);
	}

	/**
	 * Combines sequential images and make a single image that can make the motion
	 * @param  BASE64_ENCODED_DATA_URI|DIRECTLY_OUTPUT|string $path If BASE64_ENCODED_DATA_URI is passed, the raw mask image stream will be outputted directly.
	 *                                                              If DIRECTLY_OUTPUT is passed, image will be encoded as base64 that can be used in html source attribute.
	 *                                                              Else it should be the path to save the image
	 * @return string       The data uri only if BASE64_ENCODED_DATA_URI is passed as $path
	 * @throws Exception If path does not exist
	 */
	public function makeImage($path)
	{
		$im = call_user_func_array('imagecreate', $this->framesSize);

		$height = $this->framesSize[1];
		$width  = $this->framesSize[0];

		for ($x=0; $x < $width; $x++) {
			$sourceImage = $this->frameImages[$x % $this->frames];
			imagecopy($im , $sourceImage, $x, 0, $x, 0, 1, $height);
		}

		if (strtolower($path) == 'base64') return $this->imageToDataUri($im);
		$path = $this->basePath . $path;

		if (!file_exists(dirname($path)))
			throw new Exception('Path does not exist', 10);

		imagepng($im, $path);
		imagedestroy($im);
	}

	/**
	 * Generates Base64 encoded data uri to use in html source attribute
	 * @param  resource $image Generated image by GD
	 * @return string        Base64 encoded data uri
	 * @throws Exception If GD is not installed
	 */
	private function imageToDataUri(&$image)
	{
		/// TODO: php://memory is still an option if ob is not supported
		if (!function_exists('imagecreate'))
			throw new Exception('OB functions are not supported by your PHP', 6);
		
		ob_start();
		imagepng($image);
		$imageData = ob_get_contents();
		ob_end_clean();

		imagedestroy($image);

		return 'data:image/png;base64,' . base64_encode($imageData);
	}
}
