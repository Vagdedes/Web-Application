<?php

function local_product_updates(): string
{
    $bool = false;
    $products = new Account();
    $products = $products->getProduct()->find(null, false);

    if ($products->isPositiveOutcome()) {
        $platforms = array(
            AccountAccounts::SPIGOTMC_URL,
            AccountAccounts::BUILTBYBIT_URL,
            AccountAccounts::POLYMART_URL
        );
        $contentsArray = array();

        foreach ($products->getObject() as $product) {
            $versions = array();

            foreach ($platforms as $platform) {
                if (array_key_exists($platform, $product->identification)) {
                    switch ($platform) {
                        case AccountAccounts::SPIGOTMC_URL:
                            if (array_key_exists($product->identification[$platform], $contentsArray)) {
                                $contents = $contentsArray[$product->identification[$platform]];
                            } else {
                                ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36');
                                $contents = timed_file_get_contents("https://api.spigotmc.org/simple/0.2/index.php?action=getResource&id=" . $product->identification[$platform], 5);
                                $contentsArray[$product->identification[$platform]] = $contents;
                            }

                            if ($contents !== false) {
                                $object = json_decode($contents);

                                if (isset($object->current_version)) {
                                    $versions[] = $object->current_version;
                                }
                            }
                            break;
                        case AccountAccounts::BUILTBYBIT_URL:
                            $wrapper = get_builtbybit_wrapper();

                            if ($wrapper !== null) {
                                if (array_key_exists($product->identification[$platform], $contentsArray)) {
                                    $response = $contentsArray[$product->identification[$platform]];
                                } else {
                                    $response = $wrapper->resources()->versions()->latest($product->identification[$platform]);
                                    $contentsArray[$product->identification[$platform]] = $response;
                                }

                                if ($response->isSuccess()) {
                                    $response = $response->getData();

                                    if (isset($response["name"])) {
                                        $versions[] = $response["name"];
                                    }
                                }
                            }
                            break;
                        case AccountAccounts::POLYMART_URL:
                            if (array_key_exists($product->identification[$platform], $contentsArray)) {
                                $object = $contentsArray[$product->identification[$platform]];
                            } else {
                                $object = get_polymart_object(
                                    "v1",
                                    "getResourceInfo",
                                    array("resource_id" => $product->identification[$platform])
                                );
                                $contentsArray[$product->identification[$platform]] = $object;
                            }

                            if (isset($object->response->success)
                                && isset($object->response->resource->updates->latest->title)) {
                                $versions[] = $object->response->resource->updates->latest->title;
                            }
                            break;
                    }
                }
            }
            if (!empty($versions)) {
                $supportedVersion = array_shift($product->supported_versions);
                $processed = array();

                foreach ($versions as $version) {
                    $prefix = "";
                    $suffix = "";
                    $number = null;

                    foreach (explode(" ", $version) as $part) {
                        if (is_numeric($part)) {
                            if ($number !== null) {
                                break 2;
                            }
                            $number = $part;
                        } else if ($number === null) {
                            $prefix .= $part . " ";
                        } else {
                            $suffix .= $part . " ";
                        }
                    }

                    if (!empty($number)) {
                        if (empty($prefix)) {
                            $prefix = null;
                        } else {
                            $prefix = trim($prefix);
                        }
                        if (empty($suffix)) {
                            $suffix = null;
                        } else {
                            $suffix = trim($suffix);
                        }
                        if ($supportedVersion->version != $number
                            || $supportedVersion->prefix != $prefix
                            || $supportedVersion->suffix != $suffix) {
                            $hash = array_to_integer(
                                array(
                                    $number,
                                    $prefix,
                                    $suffix
                                )
                            );

                            if (!in_array($hash, $processed)) {
                                if (!empty($product->supported_versions)) {
                                    foreach ($product->supported_versions as $olderSupportedVersion) {
                                        if ($olderSupportedVersion->version == $number
                                            && $olderSupportedVersion->prefix == $prefix
                                            && $olderSupportedVersion->suffix == $suffix) {
                                            continue 2;
                                        }
                                    }
                                }
                                global $product_updates_table;
                                $processed[] = $hash;

                                if (sql_insert(
                                    $product_updates_table,
                                    array(
                                        "identification_url" => $supportedVersion->identification_url,
                                        "automated" => true,
                                        "product_id" => $product->id,
                                        "version" => $number,
                                        "name" => $supportedVersion->name,
                                        "description" => $supportedVersion->description,
                                        "prefix" => $prefix,
                                        "suffix" => $suffix,
                                        "file_name" => $supportedVersion->file_name,
                                        "file_rename" => $supportedVersion->file_rename,
                                        "file_type" => $supportedVersion->file_type,
                                        "note" => $supportedVersion->note,
                                        "required_permission" => $supportedVersion->required_permission,
                                        "creation_date" => get_current_date()
                                    )
                                )) {
                                    clear_memory();
                                    $bool = true;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return strval($bool);
}