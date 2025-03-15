<?php

class PhpAsync
{

    public static function run(
        array                 $dependencies,
        array|string|callable $method,
        array                 $parameters,
        ?bool                 $debug = null
    ): void
    {
        if (!in_array(__FILE__, $dependencies)) {
            $dependencies[] = __FILE__;
        }
        $total = "";

        if (!empty($dependencies)) {
            foreach ($dependencies as $dependency) {
                $total .= "require_once('" . $dependency . "');\n";
            }
        }
        $methodString = is_array($method)
            ? implode("::", $method)
            : $method;
        $paramsString = base64_encode(serialize($parameters));

        $total .= "var_dump(call_user_func_array('" . $methodString . "', unserialize(base64_decode('" . $paramsString . "'))));";
        $total = "php -r \"" . $total . "\"";

        if ($debug === true) {
            var_dump(shell_exec($total));
        } else if ($debug === false) {
            echo($total);
        } else {
            instant_shell_exec($total);
        }
    }

}
