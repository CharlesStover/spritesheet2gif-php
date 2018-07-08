<?php

define('CLIENT_ID',     'IMGUR_CLIENT_ID');
define('CLIENT_SECRET', 'IMGUR_CLIENT_SECRET');
define('FILESIZE_LIMIT', 1024); // kilobytes

// a file was uploaded
if (
	array_key_exists('dimension',    $_POST) &&
	array_key_exists('duration',     $_POST) &&
	array_key_exists('durationtype', $_POST) &&
	array_key_exists('matte',        $_POST) &&
	array_key_exists('tile',         $_POST) &&
	array_key_exists('sheet',        $_FILES) &&
	in_array(
		$_POST['durationtype'],
		array('frame', 'total')
	) &&
	in_array(
		$_POST['tile'],
		array('auto', 'horizontal', 'vertical')
	) &&
	preg_match('/^\d+$/',             $_POST['dimension']) &&
	preg_match('/^\d+$/',             $_POST['duration']) &&
	preg_match('/^\#[\da-zA-Z]{6}$/', $_POST['matte'])
) {
	$section_gif = '<section id="animated-gif"><h2>Animated GIF</h2>';

	function error($str, $type = '') {
		global $section_gif;
		$section_gif .= '<p><strong>' . ($type ? $type . ' ' : '') . 'Error:</strong> ' . $str . '</p>';
	}

	// If the file expired (reloading source code, browser doesn't resend file)
	if (!file_exists($_FILES['sheet']['tmp_name']))
		error('The uploaded file has expired.', 'File');

	// If the file is larger than XkB, give an error.
	else if ($_FILES['sheet']['size'] > 1024 * FILESIZE_LIMIT)
		error('The uploaded file is greater than the ' . FILESIZE_LIMIT . ' kilobyte limit.', 'File');

	// No errors.
	else {
		$size = getimagesize($_FILES['sheet']['tmp_name']);
		if ($size == false)
			error('The uploaded file is not a valid image.', 'File');
		else {
			preg_match('/^image\/(gif|jpeg|png)$/', $size['mime'], $type);
			$type = array_key_exists(1, $type) ? $type[1] : false;

			// Not a GIF, JPEG, or PNG
			if (!$type)
				error('The uploaded file is not a GIF, JPEG, or PNG.', 'Image');
			else {
				$tile_dir = $_POST['tile'];
				if ($tile_dir == 'auto')
					$tile_dir = $size[0] >= $size[1] ? 'horizontal' : 'vertical';

				$dimension = $_POST['dimension'];
				if ($dimension == 0)
					$dimension = $size[0] >= $size[1] ? $size[1] : $size[0];

				// There must be more than one frame.
				if (
					$size[0] == $dimension &&
					$size[1] == $dimension
				)
					error('The sprite sheet only contains one frame.', 'Image');

				// Sheet has to be divisible by sprite size.
				else if ($size[$tile_dir == 'horizontal' ? 0 : 1] % $dimension) {
					error(
						'The ' .
						(
							$tile_dir == 'horizontal' ?
							'width' :
							'height'
						) .
						' of the image is not divisible by ' .
						(
							$_POST['tile'] == 'auto' ?
							'the ' . (
								$tile_dir == 'horizontal' ?
								'height' :
								'width'
							) :
							$dimension
						) .
						'.',
						'Image'
					);
				}

				else {
					$count_frames = $size[$tile_dir == 'horizontal' ? 0 : 1] / $dimension;

					// convert matte from hex to rgb
					preg_match('/^\#([\da-zA-Z]{2})([\da-zA-Z]{2})([\da-zA-Z]{2})$/', $_POST['matte'], $matte);
					array_shift($matte);
					$matte = array_map('base_convert', $matte, array(16, 16, 16), array(10, 10, 10));

					$f = 'imagecreatefrom' . $type;
					$sheet = $f($_FILES['sheet']['tmp_name']);
					$frames = array();
					$duration = round($_POST['duration'] / 10); // GifCreator is off by 10x for some reason; 1 unit = 10ms
					$durations = array();

					// Calculate frames for GIF.
					$height = $tile_dir == 'horizontal' ? $size[1] : $dimension;
					$width = $tile_dir == 'horizontal' ? $dimension : $size[0];
					for ($x = 0; $x < $count_frames; $x++) {
						$frame = imagecreatetruecolor($width, $height);
						//imagesavealpha($frame, true);
						$transparent = imagecolorallocate($frame, $matte[0], $matte[1], $matte[2]);
						imagefill($frame, 0, 0, $transparent);
						imagecolortransparent($frame, $transparent);
						imagecopyresampled(
							$frame, $sheet, // to frame, from sheet
							0, 0, // to
							$tile_dir == 'horizontal' ? $x * $dimension : 0, $tile_dir == 'vertical' ? $x * $dimension : 0, // from
							$width, $height, // to width/height
							$width, $height // from width/height
						);
						array_push($frames, $frame);

						// Duration of frame
						if ($_POST['durationtype'] == 'frame')
							array_push($durations, $duration);
						else {
							$d = round($duration / ($count_frames - $x));
							$duration -= $d;
							array_push($durations, $d);
						}
					}

					// Frames -> GIF
					include 'GifCreator.php';
					$gc = new GifCreator\GifCreator();
					$gc->create($frames, $durations, 0);
					$gif = $gc->getGif();

					// Output raw GIF
					/*
					$md5 = md5($gif);
					file_put_contents('../i.charlesstover.com/cache/' . $md5 . '.gif', $gif);
					$section_gif .= '<img alt="Animated GIF" height="' . $height . '" src="data:image/gif;base64,' . base64_encode($gif) . '" width="' . $width . '" />';
					*/

					// Upload GIF to Imgur
					/*
					include '../shared/functions/imgur-upload.php';
					$gif = imgur_upload(CLIENT_ID, $gif);

					// Output HTML
					if (is_array($gif)) {
						// Array( [data] => Array ( [id] => zItS6WJ [title] => [description] => [datetime] => 1437446207 [type] => image/gif [animated] => 1 [width] => 20 [height] => 20 [size] => 17024 [views] => 0 [bandwidth] => 0 [vote] => [favorite] => [nsfw] => [section] => [account_url] => [account_id] => 0 [comment_preview] => [deletehash] => fL7L2JsPwgZe48L [name] => [gifv] => http://i.imgur.com/zItS6WJ.gifv [webm] => http://i.imgur.com/zItS6WJ.webm [mp4] => http://i.imgur.com/zItS6WJ.mp4 [link] => http://i.imgur.com/zItS6WJ.gif [looping] => 1 ) [success] => 1 [status] => 200)
						$gif = $gif['data'];
						$section_gif .=
							'<img alt="Animated GIF" height="' . $gif['height'] . '" src="' . $gif['link'] . '" width="' . $gif['width'] . '" />' .
							'<p>' .
								'<strong>Download as:</strong> ' .
								'<a href="' . $gif['link'] . '" rel="nofollow" target="_blank" title="Animated GIF">GIF</a>, ' .
								'<a href="' . $gif['mp4']  . '" rel="nofollow" target="_blank" title="MP4 Movie">MP4</a>, or ' .
								'<a href="' . $gif['gifv'] . '" rel="nofollow" target="_blank" title="WebM Video">WebM</a> ' .
							'</p>';
					}

					// String error text.
					else {
						error($gif, 'Imgur Upload');
						$section_gif .= '<img alt="Animated GIF" height="' . $height . '" src="data:image/gif;base64,' . base64_encode($gif) . '" width="' . $width . '" />';
					}*/
					$section_gif .=
						'<img alt="Animated GIF" height="' . $height . '" src="data:image/gif;base64,' . base64_encode($gif) . '" width="' . $width . '" />'; /* .
						'<p>' .
							'<strong>Download as:</strong> ' .
							'<a href="' . $gif['link'] . '" rel="nofollow" target="_blank" title="Animated GIF">GIF</a>, ' .
							'<a href="' . $gif['mp4']  . '" rel="nofollow" target="_blank" title="MP4 Movie">MP4</a>, or ' .
							'<a href="' . $gif['gifv'] . '" rel="nofollow" target="_blank" title="WebM Video">WebM</a> ' .
						'</p>';*/
				}
			}
		}
	}
	$section_gif .= '</section>';
}
else
	$section_gif = false;

?>
<!DOCTYPE html>
<html>
	<head>
		<link rel="stylesheet" type="text/css" media="screen" href="spritesheet2gif.css" />
		<title>Sprite Sheet to GIF Converter</title>
		<meta name="description" content="Convert a sprite sheet to an animated GIF!" />
		<meta name="keywords" content="animate sprite sheets, animate sprite sheets online, convert sprite sheets to animated gifs, convert sprite sheets to animated gifs online, online sprite sheet animator, online sprite sheet to animated gif converter, online sprite sheet to gif converter, online sprite sheet to gif maker, sprite sheet animator, sprite sheet to animated gif, sprite sheet to animated gif converter, sprite sheet to animated gif maker, sprite sheet to animated gif online, sprite sheet to gif, sprite sheet to gif converter, sprite sheet to gif maker, sprite sheet to gif online" />
	</head>
	<body>
		<h1>Sprite Sheet to GIF Converter</h1>
<?php

if ($section_gif)
	echo $section_gif;

else {

?>
		<section>
			<h2>Convert a Sprite Sheet to an Animated GIF</h2>
			<p>Convert your sprite sheet files to animated GIFs with this simple online tool. Browse your computer for a GIF, JPEG, or PNG sprite sheet, select the appropriate options for your animation, and click <em>Convert!</em></p>
		</section>
<?php

}

?>

		<section>
			<h2>Upload a Sprite Sheet</h2>
			<form action="/spritesheet2gif" enctype="multipart/form-data" method="post">
				<table class="bordered">
					<tbody>
						<tr>
							<th>File</th>
							<td>
								<input name="sheet" type="file" />
								<p id="max-file-size">Max file size: 1<abbr title="megabyte">MB</abbr></p>
							</td>
						</tr>
						<tr>
							<th>Duration</th>
							<td>
								<input name="duration" size="2" type="text" value="40" /> milliseconds 
								<select name="durationtype">
									<option value="frame">per frame</option>
									<option value="total">total</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>Matte</th>
							<td>
								<input name="matte" type="color" value="#f0f0f0" />
								<p>The matte color for the image will be transparent in the animated GIF.</p>

								<!-- More info... -->
								<p><a href="#" id="more-info-link">+ More Info</a></p>
								<div id="more-info">
									<p>If your sprite sheet has a <code class="colored">(240, 240, 240)</code> background color, you would want to use a <code class="colored">(240, 240, 240)</code> matte so that your animation is transparent.</p>
									<p>If your sprite sheet uses a lot of <code class="colored">(240, 240, 240)</code> in the foreground, you would <strong>not</strong> want to use a <code class="colored">(240, 240, 240)</code> matte, or else the <code class="colored">(240, 240, 240)</code> in the foreground would become transparent. An off-colored but similar alternative, such as <code class="colored alt">(241, 242, 243)</code>, that doesn't appear in the sprite sheet would be a possible solution.</p>
									<p>If your sprite sheet is already transparent, choose a color not present in the image.</p>
									<p>For translucent PNG sprite sheets, this is the background color your animated GIF will fade into, as if your PNG were placed on top of this color.</p>
								</div>
							</td>
						</tr>
						<tr>
							<th>Sprites</th>
							<td>
								Tile 
								<select id="tile" name="tile">
									<option value="auto">automatically</option>
									<option value="horizontal">horizontally</option>
									<option value="vertical">vertically</option>
								</select> with 
								<input id="dimension" name="dimension" size="1" value="0" />
								<span class="tile-auto"> pixels between sprites.</span>
								<span class="tile-horizontal">px wide sprites.</span>
								<span class="tile-vertical">px tall sprites.</span>
								<ul class="tile-auto">
									<li>
										If the sprite sheet is wider than it is tall:
										<p class="dimension-x">The sprite's width will be <span class="dimension-y">0</span>.</p>
										<p>The sprite's height <span class="dimension-zero">and width</span> will be the height of the sprite sheet.</p>
									</li>
									<li>
										If the sprite sheet is taller than it is wide:
										<p>The sprite's <span class="dimension-zero">height and</span> width will be the width of the sprite sheet.</p>
										<p class="dimension-x">The sprite's height will be <span class="dimension-y">0</span>.</p>
									</li>
								</ul>
								<p class="tile-horizontal dimension-x">The sprite's width will be <span class="dimension-y">0</span>.</p>
								<p class="tile-horizontal">The sprite's height<span class="dimension-zero"> and width</span> will be the height of the sprite sheet.</p>
								<p class="tile-vertical">The sprite's <span class="dimension-zero">height and </span>width will be the width of the sprite sheet.</p>
								<p class="tile-vertical dimension-x">The sprite's height will be <span class="dimension-y">0</span>.</p>
								<p class="dimension-x">Set the width to 0 for square sprites.</p>
							</td>
						</tr>
					</tbody>
				</table>
				<input class="raised-button" type="submit" value="Convert!" />
			</form>
		</section>
		<script type="text/javascript" src="spritesheet2gif.js"></script>
		<footer><a href="https://charlesstover.com" rel="nofollower noopener" target="_blank">&copy; Charles Stover</a></footer>
	</body>
</html>
