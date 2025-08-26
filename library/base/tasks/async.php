<?php
require '/var/www/.structure/library/base/form.php';
$function = get_form("function");

if (!empty($function)) {
    require '/var/www/.structure/library/base/communication.php';

    if (is_private_connection()) {
        require '/var/www/.structure/library/base/async.php';
        set_time_limit(PhpAsync::SECONDS_TIME_LIMIT);
        ini_set('memory_limit', PhpAsync::DEFAULT_RAM_MB . "M");
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', '1');
        ini_set('log_errors', 1);
        $function = urldecode($function);
        $parameters = urldecode(get_form("parameters"));
        $dependencies = urldecode(get_form("dependencies"));
        $debug = strtolower(trim(get_form("debug")));

        if (empty($function)) {
            error_log("PhpAsync (Website): Function is empty");
            return;
        } else {
            $function = json_decode($function, true);

            if (!is_string($function)
                && !is_array($function)
                && !is_callable($function)) {
                error_log("PhpAsync (Website): Function is not a string, array or callable");
                return;
            } else if (is_array($function)
                && sizeof($function) !== 2) {
                error_log("PhpAsync (Website): Function array does not have exactly 2 elements");
                return;
            }
        }
        if (empty($dependencies)) {
            $dependencies = array();
        } else {
            $dependencies = json_decode($dependencies, true);

            if (is_array($dependencies)) {
                foreach ($dependencies as $key => $value) {
                    if (is_string($value)) {
                        require_once $value;
                    }
                }
            }
        }
        if (empty($parameters)) {
            $parameters = array();
        } else {
            $parameters = unserialize(base64_decode(urldecode($parameters)));

            if (!is_array($parameters)) {
                $parameters = array();
            }
        }
        if ($debug == "true") {
            $outcome = call_user_func_array(
                $function,
                $parameters
            );
            error_log($outcome);
            echo $outcome;
        } else {
            call_user_func_array(
                $function,
                $parameters
            );
        }
    }
}

