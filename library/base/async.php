<?php

interface PhpAsyncSerializable
{
}

class PhpAsync
{

    private const
        SQL_TABLE = "php_async.executableTasks";

    public const
        DEFAULT_RAM_MB = 1024,
        SECONDS_TIME_LIMIT = 60 * 5;

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
        $index = array_search($dependency, $this->dependencies, true);

        if ($index !== false) {
            unset($this->dependencies[$index]);
        }
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
                "debug_code",
                "website_execution"
            ),
            array(
                array("debug_result", null)
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
                    if ($row->website_execution !== null) {
                        $this->website(
                            base64_decode($row->method_name),
                            base64_decode($row->method_parameters),
                            base64_decode($row->code_dependencies),
                            $debug
                        );
                    } else {
                        $this->run(
                            self::safeUnserialize(base64_decode($row->method_name)),
                            self::safeUnserialize(base64_decode($row->method_parameters)),
                            self::safeUnserialize(base64_decode($row->code_dependencies)),
                            $debug
                        );
                    }
                } else {
                    if ($row->website_execution !== null) {
                        $this->website(
                            base64_decode($row->method_name),
                            base64_decode($row->method_parameters),
                            base64_decode($row->code_dependencies),
                            $debug
                        );
                    } else {
                        $result = $this->run(
                            self::safeUnserialize(base64_decode($row->method_name)),
                            self::safeUnserialize(base64_decode($row->method_parameters)),
                            self::safeUnserialize(base64_decode($row->code_dependencies)),
                            $debug
                        );
                        set_sql_query(
                            self::SQL_TABLE,
                            array(
                                "debug_result" => @json_encode($result, JSON_PRETTY_PRINT),
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
        }
        return sizeof($query);
    }

    private static function allowedUnserializeClasses(): array
    {
        $allowed = [];

        foreach (get_declared_classes() as $class) {
            if (in_array(PhpAsyncSerializable::class, class_implements($class) ?: [], true)) {
                $allowed[] = $class;
            }
        }
        return $allowed;
    }

    public static function logIncompleteClasses(mixed $value, string $path = "root"): void
    {
        $seen = [];
        self::logIncompleteClassesRecursive($value, $path, $seen, 0);
    }

    private static function logIncompleteClassesRecursive(mixed $value, string $path, array &$seen, int $depth): void
    {
        if ($depth > 50) {
            return;
        }
        if ($value instanceof __PHP_Incomplete_Class) {
            $vars = (array)$value;
            $className = $vars['__PHP_Incomplete_Class_Name'] ?? "unknown";
            error_log(
                "PhpAsync: blocked class '" . $className
                . "' at " . $path . " (does not implement PhpAsyncSerializable)"
            );
            return;
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                self::logIncompleteClassesRecursive($item, $path . "[" . $key . "]", $seen, $depth + 1);
            }
            return;
        }
        if (is_object($value)) {
            $id = spl_object_id($value);

            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $reflection = new ReflectionObject($value);

            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);

                if ($property->isInitialized($value)) {
                    self::logIncompleteClassesRecursive($property->getValue($value), $path . "->" . $property->getName(), $seen, $depth + 1);
                }
            }
        }
    }

    private static function safeUnserialize(string $data): mixed
    {
        $result = unserialize($data, ['allowed_classes' => self::allowedUnserializeClasses()]);
        self::logIncompleteClasses($result);
        return $result;
    }

    public function storeAndRun(
        array|string|callable $method,
        array                 $parameters,
        array                 $dependencies = [],
        ?bool                 $debug = null,
        ?string               $expiration = null,
        bool                  $website = false
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
                "website_execution" => $website
            )
        );
    }

    private function website(
        array|string|callable $method,
        string                $parameters,
        string                $dependencies,
        ?bool                 $debug = null
    ): void
    {
        global $backup_domain;
        $this->run(
            "private_file_get_contents",
            array(
                "https://" . $backup_domain . "/async/",
                1,
                array(
                    "function" => $method,
                    "parameters" => $parameters,
                    "dependencies" => $dependencies,
                    "debug" => ($debug === null ? "null" : ($debug ? "true" : "false"))
                )
            ),
            array(
                "/var/www/.structure/library/base/utilities.php",
                "/var/www/.structure/library/base/sql.php",
                "/var/www/.structure/library/base/communication.php",
                "/var/www/.structure/library/memory/init.php"
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
        int                   $defaultRamMB = self::DEFAULT_RAM_MB,
        int                   $secondsTimeLimit = self::SECONDS_TIME_LIMIT
    ): string|false|null
    {
        if (!in_array(__FILE__, $dependencies)) {
            $dependencies[] = __FILE__;
        }
        $dependencyHash = string_to_integer(serialize($dependencies));
        $total = self::$files[$dependencyHash] ?? null;

        if ($total === null) {
            $total = "error_reporting(E_ALL);";
            $total .= "\nini_set('display_errors', 1);";
            $total .= "\nini_set('display_startup_errors', '1');";
            $total .= "\nini_set('log_errors', 1);";
            $total .= "\nini_set('memory_limit', '" . $defaultRamMB . "M');";
            $total .= "\nini_set('error_log', '/tmp/instant_php_async_run_debug.log');";
            $total .= "\nset_time_limit('" . $secondsTimeLimit . "');";

            $total .= "\nregister_shutdown_function(function() {";
            $total .= "\n  \$error = error_get_last();";
            $total .= "\n  if (\$error) {";
            $total .= "\n    file_put_contents('/tmp/instant_php_async_run_debug.log', \"Shutdown error: \" . print_r(\$error, true) . \"\\n\", FILE_APPEND);";
            $total .= "\n  }";
            $total .= "\n});";

            $total .= "\nset_error_handler(function(\$severity, \$message, \$file, \$line) {\n";
            $total .= "    file_put_contents('/tmp/instant_php_async_run_debug.log', \"[Error] \$message in \$file on line \$line\\n\", FILE_APPEND);\n";
            $total .= "});";

            $total .= "\nset_exception_handler(function(\$e) {\n";
            $total .= "    file_put_contents('/tmp/instant_php_async_run_debug.log', \"[Exception] \" . \$e->getMessage() . \" in \" . \$e->getFile() . \" on line \" . \$e->getLine() . \"\\nStack trace:\\n\" . \$e->getTraceAsString() . \"\\n\", FILE_APPEND);\n";
            $total .= "});\n";

            if (!empty($this->dependencies)) {
                foreach ($this->dependencies as $dependency) {
                    if (!empty($this->replacements)) {
                        foreach ($this->replacements as $key => $value) {
                            $dependency = str_replace($key, $value, $dependency);
                        }
                    }
                    $path = $this->directory . $dependency;
                    $total .= "require_once(base64_decode('" . base64_encode($path) . "'));\n";
                }
            }
            if (!empty($dependencies)) {
                foreach ($dependencies as $dependency) {
                    if (!empty($this->replacements)) {
                        foreach ($this->replacements as $key => $value) {
                            $dependency = str_replace($key, $value, $dependency);
                        }
                    }
                    $path = $this->directory . $dependency;
                    $total .= "require_once(base64_decode('" . base64_encode($path) . "'));\n";
                }
            }
            self::$files[$dependencyHash] = $total;
        }
        $methodString = is_array($method) ? implode("::", $method) : $method;
        $setup = "\$phpAsyncMethod = unserialize(base64_decode('" . base64_encode(serialize($methodString)) . "'), ['allowed_classes' => false]);\n"
            . "\$phpAsyncParams = unserialize(base64_decode('" . base64_encode(serialize($parameters)) . "'), ['allowed_classes' => array_values(array_filter(get_declared_classes(), function(\$c) { return in_array('PhpAsyncSerializable', class_implements(\$c) ?: [], true); }))]);\n"
            . "PhpAsync::logIncompleteClasses(\$phpAsyncParams);\n";
        $total .= $setup;
        $final = "call_user_func_array(\$phpAsyncMethod, \$phpAsyncParams);";

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
            $exec = shell_exec(
                "php -d display_errors=1 -d display_startup_errors=1 -d error_reporting=E_ALL "
                . "-d memory_limit=" . $defaultRamMB . "M -d max_execution_time=0 "
                . escapeshellarg($file)
            );
            @unlink($file);
            return $exec;
        } else if ($debug === false) {
            $total .= "var_dump(" . substr($final, 0, -1) . ");";
            return base64_encode($total);
        } else {
            $total .= $final;
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
            return instant_shell_exec(
                "php -d display_errors=1 -d display_startup_errors=1 -d error_reporting=E_ALL "
                . "-d memory_limit=" . $defaultRamMB . "M -d max_execution_time=0 "
                . escapeshellarg($file)
            );
        }
    }

}