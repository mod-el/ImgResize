<?php namespace Model\ImgResize;

class ImgResize
{
	/** @var resource */
	protected $img;
	/** @var int */
	public $w;
	/** @var int */
	public $h;
	/** @var string */
	public $mime;
	/** @var array */
	public $exif;

	/**
	 * ImgResize constructor.
	 *
	 * @param string $url
	 * @param bool $ignore_non_existent
	 * @throws \Exception
	 */
	public function __construct(string $url, bool $ignore_non_existent = false)
	{
		if (!file_exists($url) and !$ignore_non_existent) {
			throw new \Exception('Non existing image');
		}

		$size = getimagesize($url);
		$this->mime = $size['mime'];
		$this->exif = @exif_read_data($url);

		switch ($this->mime) {
			case 'image/jpeg':
				$this->img = imagecreatefromjpeg($url);
				break;
			case 'image/png':
				$this->img = imagecreatefrompng($url);
				break;
			case 'image/gif':
				$this->img = imagecreatefromgif($url);
				break;
			default:
				throw new \Exception('Image type not supported');
				break;
		}

		if (!$this->img) {
			throw new \Exception('Image file not valid');
		}

		$ort = isset($this->exif['IFD0']['Orientation']) ? $this->exif['IFD0']['Orientation'] : (isset($this->exif['Orientation']) ? $this->exif['Orientation'] : 0);

		switch ($ort) {
			case 3: // 180 rotate
				$this->img = imagerotate($this->img, 180, 0);
				break;
			case 6: // 90 rotate right
				$this->img = imagerotate($this->img, 270, 0);
				break;
			case 8:  // 90 rotate left
				$this->img = imagerotate($this->img, 90, 0);
				break;
		}

		$this->w = imagesx($this->img);
		$this->h = imagesy($this->img);
	}

	/**
	 * @return bool
	 */
	public function isValid(): bool
	{
		return (bool)(is_resource($this->img) and get_resource_type($this->img) == 'gd');
	}

	/**
	 *
	 */
	public function __destruct()
	{
		$this->destroy();
	}

	/**
	 *
	 */
	public function destroy()
	{
		if ($this->isValid())
			imagedestroy($this->img);
		$this->img = null;
	}

	/**
	 * @param array $newSizes
	 * @return resource
	 */
	public function get(array $newSizes = [])
	{
		if (isset($newSizes['w']) and !isset($newSizes['h']))
			$newSizes['h'] = $newSizes['w'] * $this->h / $this->w;
		if (isset($newSizes['h']) and !isset($newSizes['w']))
			$newSizes['w'] = $newSizes['h'] * $this->w / $this->h;

		if (isset($newSizes['w'], $newSizes['h'])) {
			$ww = $newSizes['w'];
			$hh = $newSizes['h'];
			$w = $this->w;
			$h = $this->h;

			if (!isset($newSizes['extend']))
				$newSizes['extend'] = true;

			$ratio = $w / $h;
			$rightRatio = $ww / $hh;

			$newImg = imagecreatetruecolor($ww, $hh);
			imagealphablending($newImg, false);
			ImageSaveAlpha($newImg, true);
			ImageFill($newImg, 0, 0, IMG_COLOR_TRANSPARENT);
			imagealphablending($newImg, true);

			if (!$newSizes['extend']) {
				if ($ratio < $rightRatio) {
					$new_width = $w * $hh / $h;
					imagecopyresampled($newImg, $this->img, round(($ww - $new_width) / 2), 0, 0, 0, $new_width, $hh, $w, $h);
				} else {
					$new_height = $h * $ww / $w;
					imagecopyresampled($newImg, $this->img, 0, round(($hh - $new_height) / 2), 0, 0, $ww, $new_height, $w, $h);
				}
			} else {
				if ($ratio < $rightRatio) {
					$new_height = $h * $ww / $w;
					imagecopyresampled($newImg, $this->img, 0, round(($hh - $new_height) / 2), 0, 0, $ww, $new_height, $w, $h);
				} else {
					$new_width = $w * $hh / $h;
					imagecopyresampled($newImg, $this->img, round(($ww - $new_width) / 2), 0, 0, 0, $new_width, $hh, $w, $h);
				}
			}
		} else {
			$newImg = $this->getClone();
			imagealphablending($newImg, false);
			ImageSaveAlpha($newImg, true);
			imagealphablending($newImg, true);
		}

		return $newImg;
	}

	/**
	 * @param string $url
	 * @param array $newSizes
	 * @return bool
	 * @throws \Exception
	 */
	public function save(string $url, array $newSizes = []): bool
	{
		$newImg = $this->get($newSizes);

		$mime = isset($newSizes['type']) ? $newSizes['type'] : $this->mime;

		if (file_exists($url))
			unlink($url);

		$s = false;

		switch ($mime) {
			case 'image/jpeg':
				$s = imagejpeg($newImg, $url);
				break;
			case 'image/png':
				$s = imagepng($newImg, $url);
				break;
			case 'image/gif':
				$s = imagegif($newImg, $url);
				break;
			default:
				throw new \Exception('Unsupported mime type in ImgResize save');
				break;
		}

		imagedestroy($newImg);
		unset($newImg);

		return $s;
	}

	/**
	 * @return resource
	 */
	public function getClone()
	{
		//Get sizes from image.
		$w = $this->w;
		$h = $this->h;
		//Get the transparent color from a 256 palette image.
		$trans = imagecolortransparent($this->img);

		//If this is a true color image...
		if (imageistruecolor($this->img)) {
			$clone = imagecreatetruecolor($w, $h);
			imagealphablending($clone, false);
			imagesavealpha($clone, true);
		} else {
			$clone = imagecreate($w, $h);

			//If the image has transparency...
			if ($trans >= 0) {
				$rgb = imagecolorsforindex($this->img, $trans);
				imagesavealpha($clone, true);
				$trans_index = imagecolorallocatealpha($clone, $rgb['red'], $rgb['green'], $rgb['blue'], $rgb['alpha']);
				imagefill($clone, 0, 0, $trans_index);
			}
		}

		//Create the Clone!!
		imagecopy($clone, $this->img, 0, 0, 0, 0, $w, $h);

		return $clone;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function applyWatermark(string $file)
	{
		if (!file_exists($file))
			return false;

		$size = getimagesize($file);
		$w = $this->w / 4;
		if ($w > $size[0])
			$w = $size[0];
		$h = $w * $size[1] / $size[0];

		$x = 10;
		$y = $this->h - $h - 10;

		$wm = imagecreatefrompng($file);
		return imagecopyresampled($this->img, $wm, $x, $y, 0, 0, $w, $h, $size[0], $size[1]);
	}
}
