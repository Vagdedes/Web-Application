<?php

class PhpAsync
{

    private string $directory;
    private array $replacements, $dependencies;

    public function __construct(
        string $directory = "",
        array  $replacements = [],
        array  $dependencies = []
    )
    {
        $this->directory = $directory;
        $this->replacements = $replacements;
        $this->dependencies = $dependencies;
    }

    public function run(
        array|string|callable $method,
        array                 $parameters,
        array                 $dependencies = [],
        ?bool                 $debug = null
    ): void
    {
        if (!in_array(__FILE__, $dependencies)) {
            $dependencies[] = __FILE__;
        }
        $total = "";

        if (!empty($this->dependencies)) {
            foreach ($this->dependencies as $dependency) {
                if (!empty($this->replacements)) {
                    foreach ($this->replacements as $key => $value) {
                        $dependency = str_replace($key, $value, $dependency);
                    }
                }
                $total .= "require_once('" . $this->directory . $dependency . "');\n";
            }
        }
        if (!empty($dependencies)) {
            foreach ($dependencies as $dependency) {
                if (!empty($this->replacements)) {
                    foreach ($this->replacements as $key => $value) {
                        $dependency = str_replace($key, $value, $dependency);
                    }
                }
                $total .= "require_once('" . $this->directory . $dependency . "');\n";
            }
        }
        $methodString = is_array($method)
            ? implode("::", $method)
            : $method;
        $paramsString = base64_encode(serialize($parameters));

        $total .= "call_user_func_array('" . $methodString . "', unserialize(base64_decode('" . $paramsString . "')));";

        if ($debug !== null) {
            $total = "var_dump(" . substr($total, 0, -1) . ");";
        }
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
