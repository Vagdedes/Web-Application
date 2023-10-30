<div class="footer">
    <div class="footer_top" id="opposite">
        <?php
        require_once '/var/www/.structure/library/base/utilities.php';
        $directorySlashCount = substr_count(getcwd(), "/");
        $domain = get_domain();
        ?>
        <div class="footer_left">
            <a href="https://<?php echo $domain ?>/" class="selection">IDEALISTIC AI</a>
        </div>
    </div>
</div>
