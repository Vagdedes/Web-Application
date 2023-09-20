<div class="footer">
    <div class="footer_top" id="opposite">
        <?php
        require_once '/var/www/.structure/library/base/utilities.php';
        $directorySlashCount = substr_count(getcwd(), "/");
        $domain = get_domain();

        if ($directorySlashCount > 3) {
            ?>
            <?php if ($directorySlashCount > 4) { ?>
                <a href="../" class="button" style="margin: 0px; padding: 15px;">PREVIOUS PAGE</a>
            <?php  } else { ?>
                <a href="https://<?php echo $domain ?>/account/profile" class="button" id="blue" style="margin-bottom: 0px; padding: 15px;">MY ACCOUNT</a>
            <?php  } ?>
            <a href="https://<?php echo $domain ?>/" class="button" style="margin-bottom: 0px; padding: 15px;">HOME PAGE</a>
        <?php } ?>
    </div>
</div>
