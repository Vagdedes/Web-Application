<?php

class PhpAsync
{

    private string $directory;
    private array $replacements, $dependencies;
    private static array $files = [];

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
        $dependencyHash = string_to_integer(serialize($dependencies));
        $total = self::$files[$dependencyHash] ?? null;

        if ($total === null) {
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
            self::$files[$dependencyHash] = $total;
        }
        $final = "call_user_func_array('"
            . (is_array($method) ? implode("::", $method) : $method)
            . "', unserialize(base64_decode('" . base64_encode(serialize($parameters)) . "')));";

        if ($debug === true) {
            $total .= "var_dump(" . substr($final, 0, -1) . ");";

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
            $total .= "var_dump(" . substr($final, 0, -1) . ");";
            return "php -r \"" . $total . "\"";
        } else {
            $total .= $final;

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
