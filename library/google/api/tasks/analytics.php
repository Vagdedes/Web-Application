<?php

function get_google_analytics(int $website = 1): ?string
{
    switch ($website) {
        case 1: // www.vagdedes.com
            return "<script async src='https://www.googletagmanager.com/gtag/js?id=G-3D01VKZ5Y0'></script>
                <script>
                        window.dataLayer = window.dataLayer || [];
                  function gtag(){dataLayer.push(arguments);}
                  gtag('js', new Date());
                
                  gtag('config', 'G-3D01VKZ5Y0');
                </script>";
        default:
            return null;
    }
}
