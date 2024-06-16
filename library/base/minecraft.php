<?php
$minecraft_user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36";

function get_minecraft_head_image(string $uuid, int $pixels = 100): string
{
    return "https://mc-heads.net/avatar/$uuid/$pixels/";
}

function get_minecraft_uuid(string $name, int $timeoutSeconds = 0): ?string
{
    global $minecraft_user_agent;
    ini_set("user_agent", $minecraft_user_agent);
    $result = timed_file_get_contents("https://api.mojang.com/users/profiles/minecraft/" . urlencode($name), $timeoutSeconds);

    if ($result === false) {
        return null;
    }
    $json = json_decode($result);

    if ($json == null || !is_object($json)) {
        return null;
    }
    return $json->id ?? null;
}

function get_minecraft_name(string $uuid, int $timeoutSeconds = 0): ?string
{
    global $minecraft_user_agent;
    ini_set("user_agent", $minecraft_user_agent);
    $uuid = str_replace("-", "", $uuid);
    $result = timed_file_get_contents("https://sessionserver.mojang.com/session/minecraft/profile/" . urlencode($uuid), $timeoutSeconds);

    if ($result === false) {
        return null;
    }
    $json = json_decode($result);

    if ($json == null || !is_object($json)) {
        return null;
    }
    return $json->name ?? null;
}

class MinecraftServerStatus
{

    public array $server;
    public string $motd, $error = "OK";
    public int $online_players, $max_players;
    public bool $online;

    function __construct(string $url, int|string $port = '25565')
    {
        $this->server = array(
            "url" => $url,
            "port" => $port
        );

        if ($sock = @stream_socket_client('tcp://' . $url . ':' . $port, $errno, $errstr, 1)) {
            $this->online = true;

            fwrite($sock, "\xfe");
            $h = fread($sock, 2048);
            $h = str_replace("\x00", '', $h);
            $h = substr($h, 2);
            $data = explode("\xa7", $h);
            unset($h);
            fclose($sock);

            if (sizeof($data) == 3) {
                $this->motd = $data[0];
                $this->online_players = (int)$data[1];
                $this->max_players = (int)$data[2];
            } else {
                $this->error = "Cannot retrieve server info.";
            }
        } else {
            $this->online = false;
            $this->error = "Cannot connect to server.";
        }
    }
}

class MinecraftPlatform
{

    private ?string $username;
    private ?int $id, $platform;

    public function __construct(string $url)
    {
        if (!empty($url)
            && (str_contains($url, "/members/")
                || str_contains($url, "/member/")
                || str_contains($url, "/users/")
                || str_contains($url, "/user/"))) {
            $containsHTTP = str_contains($url, "http://")
                || str_contains($url, "https://");

            if (!$containsHTTP) {
                return;
            }
            $explode = explode("/", $url, 7);
            $size = sizeof($explode);
            $username = null;
            $id = null;
            $platform = null;

            if ($size === 5 || $size === 6) {
                $trial = $explode[4];
                $explode = explode(".", $trial, 2); // Do not look further than the first dot

                if (sizeof($explode) === 2) {
                    $trial = $explode[1];

                    if (is_numeric($trial)) {
                        $id = $trial;
                        $username = $explode[0];

                        if (empty($username)) {
                            $username = null;
                        } else {
                            $url = str_replace($username, "", $url); // Remove username from URL for the search that follows
                        }
                    }
                } else if (is_numeric($trial)) {
                    $id = $trial;
                }
            }

            if ($id !== null) {
                $query = get_accepted_platforms(array("id", "name", "domain"));

                if (!empty($query)) {
                    foreach ($query as $acceptedPlatform) {
                        if (str_contains($url, $acceptedPlatform->name . "." . $acceptedPlatform->domain)) {
                            $platform = $acceptedPlatform->id;
                            break;
                        }
                    }
                    if ($platform !== null) {
                        $this->username = $username;
                        $this->id = $id;
                        $this->platform = $platform;
                    } else {
                        $this->username = null;
                        $this->id = null;
                        $this->platform = null;
                    }
                } else {
                    $this->username = null;
                    $this->id = null;
                    $this->platform = null;
                }
            } else {
                $this->username = null;
                $this->id = null;
                $this->platform = null;
            }
        } else {
            $this->username = null;
            $this->id = null;
            $this->platform = null;
        }
    }

    public function isValid(): bool // Username is optional
    {
        return $this->id != null
            && $this->platform != null;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getID(): ?int
    {
        return $this->id;
    }

    public function getPlatform(): ?int
    {
        return $this->platform;
    }
}
