<div class="footer">
    <div class="footer_top" id="opposite">
        <?php
        require_once '/var/www/.structure/library/base/utilities.php';
        $directorySlashCount = substr_count(getcwd(), "/");
        $domain = get_domain();
        ?>
        <div class="footer_left">
            <a href="https://<?php echo $domain ?>/" class="selection">VAGDEDES SERVICES</a>
        </div>
        <div class="footer_right">

        <?php
        if ($directorySlashCount > 3) {
            ?>
            <?php if ($directorySlashCount > 4) { ?>
                <a href="../" class="selection" id="hover">PREVIOUS PAGE</a>
            <?php  } else { ?>
                <a href="https://<?php echo $domain ?>/account/profile" class="selection" id="hover">MY PROFILE</a>
            <?php  } ?>
        <?php } ?>
        </div>
    </div>
</div>
