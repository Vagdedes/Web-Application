<?php
require '/var/www/.structure/library/base/form.php';
$function = get_form_post("function");

if (!empty($function)) {
    require '/var/www/.structure/library/base/communication.php';

    if (is_private_connection()) {
        require '/var/www/.structure/library/base/async.php';
        set_time_limit(PhpAsync::SECONDS_TIME_LIMIT);
        ini_set('memory_limit', PhpAsync::DEFAULT_RAM_MB . "M");
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        ini_set('log_errors', 1);
        $parameters = get_form_post("parameters");
        $dependencies = get_form_post("dependencies");
        $debug = strtolower(trim(get_form_post("debug")));

        $trustedDependencyRoot = realpath('/var/www/.structure/library/');

        $isAllowedDependency = static function (string $value) use ($trustedDependencyRoot): bool {
            if ($trustedDependencyRoot === false || !str_ends_with(strtolower($value), '.php')) {
                return false;
            }
            $resolved = realpath($value);

            return $resolved !== false
                && ($resolved === $trustedDependencyRoot
                    || str_starts_with($resolved, $trustedDependencyRoot . DIRECTORY_SEPARATOR));
        };

        $allowedUnserializeClasses = static function (): array {
            $allowed = [];

            foreach (get_declared_classes() as $class) {
                if (in_array(PhpAsyncSerializable::class, class_implements($class) ?: [], true)) {
                    $allowed[] = $class;
                }
            }
            return $allowed;
        };

        if (empty($function)) {
            error_log("PhpAsync (Website): Function is empty");
            return;
        } else {
            $function = unserialize($function, ['allowed_classes' => $allowedUnserializeClasses()]);
            PhpAsync::logIncompleteClasses($function, "function");

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
            $dependencies = unserialize($dependencies, ['allowed_classes' => $allowedUnserializeClasses()]);
            PhpAsync::logIncompleteClasses($dependencies, "dependencies");

            if (is_array($dependencies)) {
                foreach ($dependencies as $key => $value) {
                    if (is_string($value) && $isAllowedDependency($value)) {
                        require_once $value;
                    } else if (is_string($value)) {
                        error_log("PhpAsync (Website): Rejected dependency path outside trusted root: " . $value);
                    }
                }
            }
        }
        if (empty($parameters)) {
            $parameters = array();
        } else {
            $parameters = unserialize($parameters, ['allowed_classes' => $allowedUnserializeClasses()]);
            PhpAsync::logIncompleteClasses($parameters, "parameters");

            if (!is_array($parameters)) {
                $parameters = array();
            }
        }
        if ($debug == "true") {
            error_log(serialize($function));
            error_log(serialize($parameters));
            $outcome = call_user_func_array(
                $function,
                $parameters
            );
            error_log(serialize($outcome));
            echo $outcome;
        } else {
            call_user_func_array(
                $function,
                $parameters
            );
        }
    }
}