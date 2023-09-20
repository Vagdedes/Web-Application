<div class="footer">
    <div class="footer_top">
        <?php
        require_once '/var/www/.structure/library/base/utilities.php';
        $directorySlashCount = substr_count(getcwd(), "/");
        $domain = get_domain();

        if ($directorySlashCount > 3) {
            ?>
            <?php if ($directorySlashCount > 4) { ?>
                <a href="../" class="button" style="margin-bottom: 8px; padding: 15px;">PREVIOUS PAGE</a>
            <?php } else { ?>
                <a href="https://<?php echo $domain ?>/contact" class="button" id="green" style="margin-bottom: 8px; padding: 15px;">CONTACT US</a>
            <?php } ?>
            <a href="https://<?php echo $domain ?>/" class="button" style="margin-bottom: 8px; padding: 15px;">HOME PAGE</a>
        <?php } ?>
    </div>
    <div class="footer_bottom">
        @<?php echo date("Y") . " Vagdedes Services"; ?>
        <br>
        <div style="font-size: 12px;">
            All rights reserved.
        </div>
    </div>
</div>
