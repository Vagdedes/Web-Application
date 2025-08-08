<?php

class PhpAsync
{

    private const SQL_TABLE = "php_async.executableTasks";

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

    public function executeStored(int $limit = 0): int
    {
        $query = get_sql_query(
            self::SQL_TABLE,
            array(
                "id",
                "method_name",
                "method_parameters",
                "code_dependencies",
                "debug_code"
            ),
            array(
                array("debug_result", null),
            ),
            null,
            $limit
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $debug = $row->debug_code === null
                    ? null
                    : $row->debug_code == 1;

                if ($debug === null) {
                    delete_sql_query(
                        self::SQL_TABLE,
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    );
                    $this->run(
                        unserialize(base64_decode($row->method_name)),
                        unserialize(base64_decode($row->method_parameters)),
                        unserialize(base64_decode($row->code_dependencies)),
                        $debug
                    );
                } else {
                    $result = $this->run(
                        unserialize(base64_decode($row->method_name)),
                        unserialize(base64_decode($row->method_parameters)),
                        unserialize(base64_decode($row->code_dependencies)),
                        $debug
                    );
                    set_sql_query(
                        self::SQL_TABLE,
                        array(
                            "debug_result" => json_encode($result, JSON_PRETTY_PRINT),
                        ),
                        array(
                            array("id", $row->id),
                        ),
                        null,
                        1
                    );
                }
            }
        }
        return sizeof($query);
    }

    public function storeAndRun(
        array|string|callable $method,
        array                 $parameters,
        array                 $dependencies = [],
        ?bool                 $debug = null,
        ?string               $expiration = null
    ): void
    {
        sql_insert(
            self::SQL_TABLE,
            array(
                "method_name" => base64_encode(serialize($method)),
                "method_parameters" => base64_encode(serialize($parameters)),
                "code_dependencies" => base64_encode(serialize($dependencies)),
                "debug_code" => $debug === null ? null : ($debug ? 1 : 0),
                "creation_date" => get_current_date(),
                "expiration_date" => $expiration === null ? null : get_future_date($expiration),
            )
        );
    }

    /**
     * @throws Exception
     */
    public function run(
        array|string|callable $method,
        array                 $parameters,
        array                 $dependencies = [],
        ?bool                 $debug = null,
        bool                  $nonFileExecution = false
    ): string|false|null
    {
        if (!in_array(__FILE__, $dependencies)) {
            $dependencies[] = __FILE__;
        }
        $dependencyHash = string_to_integer(serialize($dependencies));
        $total = self::$files[$dependencyHash] ?? null;

        if ($total === null) {
            $total = "error_reporting(E_ALL);\nini_set('display_errors', 1);\n";

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
            $file = tempnam(sys_get_temp_dir(), "php_async_");

            if ($file === false) {
                throw new Exception("Failed to create temporary PHP async file (1).");
            }
            $total .= "\nunlink(__FILE__);";
            $put = file_put_contents($file, "<?php\n" . $total);

            if ($put === false) {
                @unlink($file);
                throw new Exception("Failed to write PHP async file (1): " . $file);
            }
            @chmod($file, 0644);
            $newFile = $file . '.php';

            if (!rename($file, $newFile)) {
                @unlink($file);
                throw new Exception("Failed to rename temporary PHP async file (1): " . $file);
            }
            $file = $newFile;
            clearstatcache(true, $file);

            if (!is_readable($file)) {
                @unlink($file);
                throw new Exception("PHP async file is not readable (1): " . $file);
            }
            $exec = shell_exec("php " . escapeshellarg($file));
            unlink($file);
            return $exec;
        } else if ($debug === false) {
            $total .= "var_dump(" . substr($final, 0, -1) . ");";
            return base64_encode($total);
        } else {
            $total .= $final;

            if ($nonFileExecution
                && strlen($total) <= 2_097_152 - 32) {
                return instant_shell_exec("php -r \"" . $total . "\"");
            } else {
                $file = tempnam(sys_get_temp_dir(), "php_async_");

                if ($file === false) {
                    throw new Exception("Failed to create temporary PHP async file (2).");
                }
                $total .= "\nunlink(__FILE__);";
                $put = file_put_contents($file, "<?php\n" . $total);

                if ($put === false) {
                    @unlink($file);
                    throw new Exception("Failed to write PHP async file (2): " . $file);
                }
                @chmod($file, 0644);
                $newFile = $file . '.php';

                if (!rename($file, $newFile)) {
                    @unlink($file);
                    throw new Exception("Failed to rename temporary PHP async file (2): " . $file);
                }
                $file = $newFile;
                clearstatcache(true, $file);

                if (!is_readable($file)) {
                    @unlink($file);
                    throw new Exception("PHP async file is not readable (2): " . $file);
                }
                return instant_shell_exec("php " . escapeshellarg($file));
            }
        }
    }

}
