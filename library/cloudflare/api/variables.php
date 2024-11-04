<?php

class CloudflareVariables
{

    public const
        CLOUDFLARE_API_VERSION = "v4",
        CLOUDFLARE_CREDENTIALS_DIRECTORY = "cloudflare_credentials",
        CLOUDFLARE_REQUEST_TIMEOUT_SECONDS = 25,
        CLOUDFLARE_ZONES = array(
        "idealistic.ai" => "59c7cbad983a3d60a4ad734567d5b8d9"
    );

}

class CloudflareConnectionType
{
    public const
        GET = "GET",
        POST = "POST",
        PUT = "PUT",
        DELETE = "DELETE";
}
