<?php
$minecraft_user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36";

function get_minecraft_head_image($uuid, $pixels = 100)
{
    return "https://mc-heads.net/avatar/$uuid/$pixels/";
}

function get_minecraft_uuid($name, $timeoutSeconds = 0)
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

function get_minecraft_name($uuid, $timeoutSeconds = 0)
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

    public $server;
    public $online, $motd, $online_players, $max_players;
    public $error = "OK";

    function __construct($url, $port = '25565')
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
