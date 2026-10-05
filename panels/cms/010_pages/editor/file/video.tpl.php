<?php
$aspect = $media->width && $media->height ? $media->width / $media->height : '16/9';
?>
<div class='imageCropAlignContainer'>
	<div>	
		<video id='videosnapshot' width="100%" data-node_id="<?= $this->targetId ?>" controls>
			<source src="<?= $original->src ?>" type="video/mp4">
		</video> 
		<br clear="all">
		<br clear="all">
		<button id="snap" onclick="snap(), this.classList.add('loading')" data-spinner="true" class="button submit">Create Poster from Frame</button>
		<br clear="all">
		<br clear="all">
		<h2>Poster Image</h2>
		<div class="posterImageContainer" style='aspect-ratio:<?= $aspect ?>'>
			<canvas id='snapshotpreview'></canvas>
			<?php if (is_file(\fs::toNative(str_replace('.mp4', '', $original->path) . '.webp'))) { ?>
				<img id='videoPosterPreview' src="<?= str_replace('.mp4', '', $original->src) . '.webp?v=' . time() ?>">
			<?php }
			?>
		</div>
	</div>
</div>
<script>
	var video = document.querySelector('video#videosnapshot');
	var canvas = document.querySelector('canvas#snapshotpreview');
	// Get a handle on the 2d context of the canvas element
	var context = canvas.getContext('2d');
	var w, h, ratio;

	// Add a listener to wait for the 'loadedmetadata' state so the video's dimensions can be read
	video.addEventListener('loadedmetadata', function () {
		// Calculate the ratio of the video's width to height
		ratio = video.videoWidth / video.videoHeight;
		// Define the required width as 100 pixels smaller than the actual video's width
		w = video.videoWidth;
		// Calculate the height based on the video's width and the ratio
		h = parseInt(w / ratio, 10);
		// Set the canvas width and height to the values just calculated
		canvas.width = w;
		canvas.height = h;
	}, false);


	function snap() {
		// Define the size of the rectangle that will be filled (basically the entire element)
		context.fillRect(0, 0, w, h);
		// Grab the image from the video
		context.drawImage(video, 0, 0, w, h);
		var canvasData = canvas.toDataURL("image/webp");
		var node_id = document.getElementById('videosnapshot').getAttribute('data-node_id');
		var xhr = new XMLHttpRequest();
		xhr.open("POST", 'createPoster?target_id=' + node_id, true);
		xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		xhr.send("value=" + encodeURIComponent(canvasData));
		xhr.onreadystatechange = function () {
			if (xhr.readyState == 4 && xhr.status == 200) {
				var image = document.getElementById('videoPosterPreview');
				var src = image.src+'1';
				image.src = src;
				document.querySelector('button#snap').classList.remove('loading');
			}
		};
	}

</script>
<style>
	canvas#snapshotpreview{
		position: absolute;
		opacity: 0;
		visibility: hidden;
	}
</style>
