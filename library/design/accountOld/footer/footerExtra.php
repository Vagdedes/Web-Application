<div class="footer">
	<div class="footer_top">
	<a href='<?php echo $object->home_page != null ? $object->home_page : ".."; ?>' class="button" style="margin-bottom: 8px; padding: 15px;">HOME PAGE</a>

	<?php
	if ($object->is_patreon && $object->discord_url != null) {
		$discord = htmlspecialchars($object->discord_url);
		echo "<a href='$discord' class='img_button' style='padding: 15px; margin-bottom: 8px; background-image: url(https://vagdedes.com/.images/discord.png);'>DC</a>";
	}
	?>
	</div>
	<div class="footer_bottom">
		@<?php echo date("Y") . " footerExtra.php" . htmlspecialchars($object->name); ?>
		<br>
		<div style="font-size: 12px;">
			All rights reserved.
		</div>
	</div>
</div>
