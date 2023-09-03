<?php
if (false) {
    $url = "https://pastebin.com/api/api_login.php";
    $api_dev_key = "28f817ee20f9da81aea9e37744a6d687";
    $api_user_name = "Vagdedes";
    $api_user_password = "angelica4ever.GAMW";

    // Request
    $data = array('api_dev_key' => $api_dev_key, 'api_user_name' => $api_user_name, 'api_user_password' => $api_user_password);
    $options = array(
        'http' => array(
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        )
    );
    //$context  = stream_context_create($options);
    //$result = file_get_contents($url, false, $context);
    //var_dump(file_get_contents($url, false, $context));
}
