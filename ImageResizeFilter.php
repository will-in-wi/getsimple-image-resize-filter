<?php
/*
Plugin Name: Image Resize Filter
Description: Resizes images in the html.
Version: 1.0
Author: William Johnston
Author URI: http://www.manystrands.com/
*/

# get correct id for plugin
$thisfile=basename(__FILE__, ".php");

# register plugin
register_plugin(
	$thisfile, //Plugin id
	'Image Resize Filter', 	//Plugin name
	'1.0', 		//Plugin version
	'William Johnston',  //Plugin author
	'http://www.manystrands.com/', //author website
	'Resizes images in HTML', //Plugin description
	'plugins', //page type - on which admin tab to display
	'image_resize_filter_admin'  //main function (administration)
);

add_action('plugins-sidebar','createSideMenu',array($thisfile, 'Image Resize Filter'));

add_filter('content', 'filter_image_resize');

define('RESIZE_CACHE_FOLDER', 'data/uploads/resize_filter');

# functions
function filter_image_resize($content) {
	
	include_once('ImageResizeFilter/simple_html_dom.php');
	$html = new simple_html_dom();
	$html->load($content);
	
	// Check for resize folder and create if it doesn't exist.
	if (!is_dir(RESIZE_CACHE_FOLDER)){
		mkdir(RESIZE_CACHE_FOLDER);
	}
	
	$images = $html->find('img');
	foreach ($images as $i => $image) {
		$currFileName = substr($image->src, strlen(get_site_url(false)));
		
		$imageWidth = false;
		$imageHeight = false;
		foreach (explode(';', $image->style) as $style) {
			if (strripos(trim($style), 'width:') !== false) {
				$imageWidth = trim(substr(trim($style), strlen('width:')));
				// Check for 'px' and remove if extant.
				if (strripos($imageWidth, 'px') !== false) {
					$imageWidth = (int) substr($imageWidth, 0, strlen($imageWidth) - strlen('px'));
				} else {
					$imageWidth = 0;
				}
			} else if (strripos(trim($style), 'height:') !== false) {
				$imageHeight = trim(substr(trim($style), strlen('height:')));
				// Check for 'px' and remove if extant.
				if (strripos($imageHeight, 'px') !== false) {
					$imageHeight = (int) substr($imageHeight, 0, strlen($imageHeight) - strlen('px'));
				} else {
					$imageHeight = 0;
				}
			}
		}
		if ($imageWidth === 0 || $imageHeight === 0 || ($imageWidth === false && $imageHeight == false)) {
			continue; // Ignore images with strange height's or width's.
		}
		
		list($sourceImageWidth, $sourceImageHeight, $sourceImageType) = getimagesize($currFileName);
		
		// Calculate missing dimensions.
		if ($imageWidth === false) {
			$ratio = $imageHeight / $sourceImageHeight;
			$imageWidth = $sourceImageWidth * $ratio;
		}
		if ($imageHeight === false) {
			$ratio = $imageWidth / $sourceImageWidth;
			$imageHeight = $sourceImageHeight * $ratio;
		}
		
		// Determine if the image is already the correct height and width.
		if ($sourceImageWidth == $imageWidth && $sourceImageHeight == $imageHeight) {
			continue;
		}
		
		// Generate new location and filename.
		$tempFilePath = substr($currFileName, strlen('data/uploads'));
		$newFileName = RESIZE_CACHE_FOLDER . substr($tempFilePath, 0, strripos($tempFilePath, '.')) . '-' . $imageWidth . 'x' . $imageHeight . substr($tempFilePath, strripos($tempFilePath, '.'));
		if (!file_exists($newFileName)) {
			// If not available, resize and save.
			switch ($sourceImageType) {
			  case IMAGETYPE_GIF:
				$sourceGDImage = imagecreatefromgif($currFileName);
				break;
			  case IMAGETYPE_JPEG:
				$sourceGDImage = imagecreatefromjpeg($currFileName);
				break;
			  case IMAGETYPE_PNG:
				$sourceGDImage = imagecreatefrompng($currFileName);
				break;
			}

			if ($sourceGDImage === false ) {
				continue;
			}

			$resizedGDImage = imagecreatetruecolor($imageWidth, $imageHeight);

			imagecopyresampled($resizedGDImage, $sourceGDImage, 0, 0, 0, 0, $imageWidth, $imageHeight, $sourceImageWidth, $sourceImageHeight);
			
			// Create subfolders if not yet created
			if (!is_dir(substr($newFileName, 0, strripos($newFileName, '/')))) {
				mkdir(substr($newFileName, 0, strripos($newFileName, '/')), 0777, true);
			}
			
			switch ($sourceImageType) {
			  case IMAGETYPE_GIF:
				imagegif($resizedGDImage, $newFileName);
				break;
			  case IMAGETYPE_JPEG:
				imagejpeg($resizedGDImage, $newFileName, 90);
				break;
			  case IMAGETYPE_PNG:
				imagepng($resizedGDImage, $newFileName);
				break;
			}
			imagedestroy($sourceGDImage);
			imagedestroy($resizedGDImage);
		}
		$html->find('img', $i)->src = get_site_url(false) . $newFileName;
	}
	
	return($html->save());
}

function image_resize_filter_admin() {
	echo '<h3>Image Resize Filter</h3>';
	
	// Check for a submitted form.
	if(isset($_POST['resetCache'])) {
		$dirIter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator('../' . RESIZE_CACHE_FOLDER),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach($dirIter as $file) {
			if (in_array($file->getBasename(), array('.', '..'))) {
				continue;
			} elseif ($file->isDir()) {
				try {
					rmdir($file->getPathname());
				} catch (Exception $e) {
					echo 'Problem with directory: ' . $file->getPathname();
					echo 'Error message: ' . $e->getMessage();
				}
			} elseif ($file->isFile() || $file->isLink()) {
				try {
					unlink($file->getPathname());
				} catch (Exception $e) {
					echo 'Problem with file: ' . $file->getPathname();
					echo 'Error message: ' . $e->getMessage();
				}
			}
		}
		
		echo '<div class="updated"><p>Cache cleared</p></div>';
	}
	?>
	<form method="post" id="resetImageResizeCache" action="<?php	echo $_SERVER ['REQUEST_URI']?>">
		<input type="submit" class="submit" value="Reset Cache" name="resetCache" /><br /><br />
		<p>This deletes all of the cached images, freeing space and eliminating unused images, but also slowing initial pageloads after completion.</p>
	</form>
	<?php
}
?>