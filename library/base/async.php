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

    public function setDirectory(
        string $directory
    ): void
    {
        $this->directory = $directory;
    }

    public function addReplacement(
        string $key,
        string $value
    ): void
    {
        $this->replacements[$key] = $value;
    }

    public function removeReplacement(
        string $key
    ): void
    {
        unset($this->replacements[$key]);
    }

    public function addDependency(
        string $dependency
    ): void
    {
        $this->dependencies[] = $dependency;
    }

    public function removeDependency(
        string $dependency
    ): void
    {
        unset($this->dependencies[array_search($dependency, $this->dependencies)]);
    }

    // Separator

    public function run(
        array|string|callable $method,
        array                 $parameters,
        array                 $dependencies = [],
        ?bool                 $debug = null
    ): string|false|null
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

        $final = "call_user_func_array('" . $methodString . "', unserialize(base64_decode('" . $paramsString . "')));";

        if (is_bool($debug)) {
            $total .= "var_dump(" . substr($final, 0, -1) . ");";
        } else {
            $total .= $final;
        }

        if ($debug === true) {
            while (true) {
                $file = sys_get_temp_dir() . "/" . random_string(32) . ".php";

                if (file_exists($file)) {
                    continue;
                }
                $put = @file_put_contents($file, "<?php\n" . $total);

                if ($put === false) {
                    return null;
                }
                $exec = shell_exec("php " . $file);
                @unlink($file);
                return $exec;
            }
        } else if ($debug === false) {
            return "php -r \"" . $total . "\"";
        } else {
            while (true) {
                $file = sys_get_temp_dir() . "/" . random_string(32) . ".php";

                if (file_exists($file)) {
                    continue;
                }
                $total .= "\n@unlink(__FILE__);";
                $put = @file_put_contents($file, "<?php\n" . $total);

                if ($put === false) {
                    return null;
                }
                return instant_shell_exec("php " . $file);
            }
        }
    }

}
