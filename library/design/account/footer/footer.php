<div class="footer">
    <div class="footer_center">
        <div class="footer_top">
            <?php
            require_once '/var/www/.structure/library/base/utilities.php';
            $domain = get_domain();
            ?>
            <a href="https://<?php echo $domain ?>/contact" class="selection" id="hover">CONTACT US</a>
        </div>
        <div class="footer_bottom">
            @<?php echo date("Y") . " Idealistic AI"; ?>
            <br>
            <div style="font-size: 12px;">
                All rights reserved.
            </div>
        </div>
    </div>
</div>
