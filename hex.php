<?
/*
Usage:

$image = new hex_image;
$image->set_color(...);
$image->set_shape(...);
$image->start();	// can't call previous functions after this, or following functions before
$image->hextext(...);
$image->hexfill(...);
$image->hexborder(...);
$image->hexhighlight(...);
$image->finish();	// calls imagepng()
*/

define("NE", 0);
define("E", 1);
define("SE", 2);
define("SW", 3);
define("W", 4);
define("NW", 5);

class hex_image {
	var $image, $image_color, $color;
	var $xsize, $ysize, $l, $b, $shape;

	function Hex() {		// constructor
		$this->xsize = $this->ysize = $this->l = $this->b = 0;
		$this->shape = $this->image_color = $this->color = array();
	}

	function hexwidth() {		// returns the width of a hex
		return $this->get_b() * 2;
	}

	function get_l() {
		return $this->l;
	}
	function set_l($l) {
		$this->l = $l;
		$this->b = sin(deg2rad(60)) * $l;
	}
	function get_b() {		// returns the offset for hexes
		return $this->b;
	}
	function get_size() {
		return array($this->xsize, $this->ysize);
	}
	function set_size($x, $y) {
		$this->xsize = $x;
		$this->ysize = $y;
	}
	function set_color($name, $r, $g, $b) {
		$this->color[$name] = array('r' => $r, 'g' => $g, 'b' => $b);
	}

	function center_x($x, $y) {	// gives pixel x for center of hex x
		$x--;$y--;
		if ($y % 2)	//even
			return $this->hexwidth() * $x + $this->hexwidth();
		else		//odd
			return $this->hexwidth() * $x + $this->hexwidth()/2;
	}
	function center_y($x, $y) {	// gives pixel y for center of hex y
		$x--;$y--;
		return $this->l * 1.5 * $y + $this->l;
	}
	function steps($x1, $y1, $x2, $y2) {
		$dx = abs( $x1 * 2 + (($y1 % 2) ? 0 : 1) - ($x2 * 2 + (($y2 % 2) ? 0 : 1)) );
		$dy = abs( $y1 - $y2 );
		$dist = floor( max( ($dx+$dy)/2, $dy) );

		return $dist;
	}

	function set_shape($type, $shape = array()) {
		switch($type) {
		case "grid":		// ignores shape, uses x and y
			for($x = 1; $x <= $this->xsize; $x++)
				for($y = 1; $y <= $this->ysize; $y++)
					$this->shape[$x][$y] = 'white';
			break;

		case "circle":		// in this case, $shape will be the radius, sets x and y
			$radius = $shape;
			$this->xsize = $this->ysize = $radius * 2 - 1;
			for($x = 1; $x <= $this->xsize; $x++)
				for($y = 1; $y <= $this->ysize; $y++)
					if ($this->steps($x, $y, $radius, $radius) < $radius)
						$this->shape[$y][$x] = 'white';
					else
						$this->shape[$y][$x] = '';
			break;

		case "free":		// takes shape on faith, sets x and y size
			$this->xsize = $this->ysize = 0;
			foreach($shape as $a) {
				if ($a['x'] > $this->xsize)
					$this->xsize = $a['x'];
				if ($a['y'] > $this->ysize)
					$this->ysize = $a['y'];
				$this->shape[$a['y']][$a['x']] = 'white';
			}
			break;
		}
	}

	function start() {
		$this->image = imagecreate($this->xsize * $this->hexwidth() + $this->hexwidth()/2+1, $this->ysize * $this->l * 1.5 + $this->l/2+1);

		// if they specifically gave a background, we'll use it -- otherwise black
		if ($this->color['bg'])
			$this->image_color['bg'] = imagecolorallocate($this->image, $this->color['bg']['r'], $this->color['bg']['g'], $this->color['bg']['b']);
		$this->image_color['black'] = imagecolorallocate($this->image, 0, 0, 0);
		$this->image_color['gray'] = imagecolorallocate($this->image, 96, 96, 96);
		$this->image_color['white'] = imagecolorallocate($this->image, 255, 255, 255);

		// load up all the user colors
		foreach($this->color as $name => $rgb)
			$this->image_color[$name] = imagecolorallocate($this->image, $rgb['r'], $rgb['g'], $rgb['b']);

		$this->drawshape();
	}
	function finish() {
		imagepng($this->image);
		imagedestroy($this->image);
	}

// internals
	function drawshape() {
		for($x = 1; $x <= $this->xsize; $x++)
			for($y = 1; $y <= $this->ysize; $y++)
				if ($this->shape[$y][$x])
					$this->drawhex($x, $y, $this->shape[$y][$x], 'gray');
	}
	function drawhex($hx, $hy, $color, $border) {
		$x = $this->center_x($hx, $hy);
		$y = $this->center_y($hx, $hy);

		$b = $this->get_b();
		$l = $this->get_l();
		$points = array(
			$x,	$y-$l,
			$x+$b,	$y-$l/2,
			$x+$b,	$y+$l/2,
			$x,	$y+$l,
			$x-$b,	$y+$l/2,
			$x-$b,	$y-$l/2,
			$x,	$y-$l
		);

		imagefilledpolygon($this->image, $points, 7, $this->image_color[$color]);
		imagepolygon($this->image, $points, 7, $this->image_color[$border]);
	}
	function neighbor($x, $y, $side) {	// gives an array of x, y for which hex is touching the given hex on the given side
		if ($y % 2) {	// odd
			switch($side) {
			case NE:	return array('x' => $x,		'y' => $y-1);
			case E:		return array('x' => $x+1,	'y' => $y);
			case SE:	return array('x' => $x,		'y' => $y+1);
			case SW:	return array('x' => $x-1,	'y' => $y+1);
			case W:		return array('x' => $x-1,	'y' => $y);
			case NW:	return array('x' => $x-1,	'y' => $y-1);
			}
		} else {	// even
			switch($side) {
			case NE:	return array('x' => $x+1,	'y' => $y-1);
			case E:		return array('x' => $x+1,	'y' => $y);
			case SE:	return array('x' => $x+1,	'y' => $y+1);
			case SW:	return array('x' => $x,		'y' => $y+1);
			case W:		return array('x' => $x-1,	'y' => $y);
			case NW:	return array('x' => $x,		'y' => $y-1);
			}
		}
	}
	function wall($hx, $hy, $dir, $color) {	// draws a line on the side of the hex
		$x = $this->center_x($hx, $hy);
		$y = $this->center_y($hx, $hy);
		$b = $this->get_b();
		$l = $this->get_l();

		switch($dir) {
		case NE:
			imageline($this->image,	$x,		$y-$l,		$x+$b,		$y-$l/2,	$this->image_color[$color]);
			imageline($this->image,	$x,		$y-$l+1,	$x+$b-1,	$y-$l/2,	$this->image_color[$color]);
			imageline($this->image,	$x,		$y-$l+2,	$x+$b-2,	$y-$l/2,	$this->image_color[$color]);
			break;
		case E:
			imageline($this->image,	$x+$b,		$y-$l/2,	$x+$b,		$y+$l/2,	$this->image_color[$color]);
			imageline($this->image,	$x+$b-1,	$y-$l/2,	$x+$b-1,	$y+$l/2,	$this->image_color[$color]);
			imageline($this->image,	$x+$b-2,	$y-$l/2,	$x+$b-2,	$y+$l/2,	$this->image_color[$color]);
			break;
		case SE:
			imageline($this->image,	$x+$b,		$y+$l/2,	$x,		$y+$l,		$this->image_color[$color]);
			imageline($this->image,	$x+$b,		$y-1+$l/2,	$x,		$y+$l-1,	$this->image_color[$color]);
			imageline($this->image,	$x+$b,		$y-2+$l/2,	$x,		$y+$l-2,	$this->image_color[$color]);
			break;
		case SW:
			imageline($this->image,	$x,		$y+$l,		$x-$b,		$y+$l/2,	$this->image_color[$color]);
			imageline($this->image,	$x,		$y+$l-1,	$x-$b,		$y+$l/2-1,	$this->image_color[$color]);
			imageline($this->image,	$x,		$y+$l-2,	$x-$b,		$y+$l/2-2,	$this->image_color[$color]);
			break;
		case W:
			imageline($this->image,	$x-$b,		$y+$l/2,	$x-$b,		$y-$l/2,	$this->image_color[$color]);
			imageline($this->image,	$x-$b+1,	$y+$l/2,	$x-$b+1,	$y-$l/2,	$this->image_color[$color]);
			imageline($this->image,	$x-$b+2,	$y+$l/2,	$x-$b+2,	$y-$l/2,	$this->image_color[$color]);
			break;
		case NW:
			imageline($this->image,	$x-$b,		$y-$l/2,	$x,		$y-$l,		$this->image_color[$color]);
			imageline($this->image,	$x-$b+1,	$y-$l/2,	$x,		$y-$l+1,	$this->image_color[$color]);
			imageline($this->image,	$x-$b+2,	$y-$l/2,	$x,		$y-$l+2,	$this->image_color[$color]);
			break;
		}
	}

// publicly useful
	function highlight($x, $y, $color) { // border all sides of a hex
		$this->wall($x, $y, NE, $color);
		$this->wall($x, $y, E, $color);
		$this->wall($x, $y, SE, $color);
		$this->wall($x, $y, SW, $color);
		$this->wall($x, $y, W, $color);
		$this->wall($x, $y, NW, $color);
	}
	function border($region, $color) {	// takes an array of hexes (x, y) and borders them
		//print_r($region);
		foreach($region as $hex) {
			$x = $hex['x'];
			$y = $hex['y'];

			if (!in_array($this->neighbor($x, $y, NE), $region))	$this->wall($x, $y, NE, $color);
			if (!in_array($this->neighbor($x, $y, E), $region))	$this->wall($x, $y, E, $color);
			if (!in_array($this->neighbor($x, $y, SE), $region))	$this->wall($x, $y, SE, $color);
			if (!in_array($this->neighbor($x, $y, SW), $region))	$this->wall($x, $y, SW, $color);
			if (!in_array($this->neighbor($x, $y, W), $region))	$this->wall($x, $y, W, $color);
			if (!in_array($this->neighbor($x, $y, NW), $region))	$this->wall($x, $y, NW, $color);
		}
	}
	function fill($hx, $hy, $color) {	// fills a hex with a color
		$x = $this->center_x($hx, $hy);
		$y = $this->center_y($hx, $hy);

		imagefill($this->image, $x, $y, $this->image_color[$color]);
	}
	function text($hx, $hy, $color, $text) {	// puts a label in the center of a hex
		$x = $this->center_x($hx, $hy);
		$y = $this->center_y($hx, $hy);
		$text = trim($text);

		// print in center of hex
		$x = $x - imagefontwidth(2)/2 * strlen($text) + 1;
		$y = $y - imagefontheight(2)/2;

		imagestring($this->image, 2, $x, $y, $text, $this->image_color[$color]);
	}

}

?>
