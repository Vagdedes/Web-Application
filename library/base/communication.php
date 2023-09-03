<?php
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/default_sql.php';
require_once '/var/www/.structure/library/memory/init.php';
$administrator_local_server_ip_addresses_table = "administrator.localServerIpAddresses";
$website_communication_code = str_replace(
    "\n", "",
    "fRKI0f2ykv2e5vfrh9drXfUeAJ3WYTTaWEzLagLN
MSFd4wWr19CxMLI6BTEi1xqS2kEDqi9gNBlM87Ox4I1NhY2vpuLw4t7NSlbyV4vYLN4
LOv3MIaHLFQ8x4Bg6XRzuKZC7NjQi4iz6u7gtpLXZSVlyT6tPR8TE8XWOZUC4cLe5i1Z
5xaHOBiJxKbKhLUwSJeOX6DsLNRbzQEBC16oCrGICCTES04Rfh4dRwSlsllfC7cCBPHo
ss34CM54V5U1s8wHoulP3WCze140CaIcejLebRZKwJR5wtOHi7Gf6SgvA5OO51V6N3
v9hvPpwOO3T0XWH9uTbqRazrTKUc9QRsUTtms1PMq9t44Uzcp7bUDRrpKRkYcjamlJ
KoiUfkB8Hz1nQZYKZIppzQvd3kquq0yecLpHeSpmElJfE7znFjiYDGAqDONNV8esKRc4
4jrhj3sncjxslqmFgd766PYNy0P4RLLg7FckcnPfbnCny4GBo3ouSjJUij5F6zRAkbYkjB5o
RhgTgAmUf4KQgYcXvorubJH0effw1uvG00w6ex61hEq9kUVXkDHVfmnZWtAJydU4dk
svwHDKT8joAfs9G5m8NGx4ZkGJ47V2QH2pukn4i5qEwqXHwOo3gpA2l0iWFNKWBx
Q8kBHvCAQFoeVbfJDbqJfWv3Qth29pJipBkIUpmT17hcKX8zpcnz2WmCD9Axf74EonS
3D8kLDhcYFj11SYzaXvww0tkt9NvfK3FeU2lsvtPeQyt3ko6IJken7F040hzrGNpa2difNl
K0aa6SpT5oAdnKV8x0450t1AXx1EiE4gkKbHUflP5ZbEkx4BDV2QkC4sOl57suxXC9O
shSIF9h7VQOqtxddErOp2fpzU9uqNqJ2WpWd5zMuWo2s4yIwZpsjfZS5bvnsONLQLxd
Yl2wWFJCTdzza40h61UdVAezl3mEI9m0lBfC685dTW1w5bNdcu9aLynZ8XW6kr7Wh2
pbcU7UC0Rl9YGA3bvHNw6f1MwyGw0MCFl2jziiVFe9CY7ZBinxl3vyYjU7phB54fv6PV6
mAGCJ2gyz2xLA7gPkwjf3kiMF4AFBQz9cbvjUV7ckc48fZoMEzcYZ94xSUNqYGFqFeUI
Sk9AYSklh7MMjeIFlyP34QYlpBoleZ0vgtCPPmhvjBYawIMGjOGDotrUiFja9pClsf1lZVF1
lekImDOxLWMK6BczN3pRcdiLiEuyRK7clWkvSg4JgOcyKk7dCH8OPew1W4tqBKfMN4
E6miCTahIAQ2oWtk0UnPcHd8w0W9cahyZpU0Sg5gxQvJAfC6AEbcCGgseUB5lthDulY
QcES51VDZHP18MUwcr9m5CiFBw8lyAHXFHkEtF9UgpMLtsjylOcGe4R7smknV0E5C4
BHnbpD7W1ODx9b1Si8KygvsUoFnJm1krsFjgu5NIjxJ9DOkkrVgK3d6EWEwK0PFnnX
ELlS5JAIyqKHozgEmhelAQrOW33L7VRtf9AfAAFgho7AOXAIAPbrqTFLSpEUE326QGh
cSGTwB9s9iH6UsjMfLZCF8LuV4AaLBKSCa1LoDH8EZNaT4U6bxJSRrDvvrZju7a3EJV
ql8yJ3zuOV3KI0mAxaOfVV0QrXmbMDMxnupLI29wLi9XToiz8zMTlO7wDPv4QpH01D
ad86RBqqCFR77YsFWYHAPuzbPPHSE0mWlM0RUyvINvnizwBvE8fWxAMJ97PbYxpyf2
xU200rlCwH27bUTfCoe7TIIcU4mCLvDtJFRCaCkgxnYdEARgEt246WpQQIypaDnwtb4
NcRX84fyw08miHdpJS0VM5wCSEC6GvhGPM32BkMNeY18f0j0kPOaMq31cZSkXqk9D
BclookMtzYQKKV5dzk5TOfGELUkdJ2guMSd1F1wvfmVQVqYy6w9W3FuZgFG0y0OJQn
cxDxm7q1C7lQFTKOCCDmSIrUWJXOkk2TxOrF8jolqVHPd5iNpKs7emvX6l3BeqBOtaH
2wUGOJjtfFnhMF1MXpOre2yX7zvK3TI2xyaH4Nm34gZsQtIs4majnaq44MxK"
);

function private_file_get_contents($url, $createAndClose = false)
{
    global $website_communication_code;
    return post_file_get_contents(
        $url,
        array(
            "private_verification_key" => $website_communication_code,
            "private_ip_address" => get_client_ip_address()
        ),
        false,
        $_SERVER['HTTP_USER_AGENT'] ?? "",
        $createAndClose ? 1 : 0
    );
}

// Separator

function is_private_connection(): bool
{
    global $website_communication_code;

    if (($_POST['private_verification_key'] ?? "") === $website_communication_code) {
        global $administrator_local_server_ip_addresses_table;
        set_sql_cache("1 minute");
        return !empty(get_sql_query(
            $administrator_local_server_ip_addresses_table,
            array("id"),
            array(
                array("ip_address", get_local_ip_address()),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null,
            ),
            null,
            1
        ));
    }
    return false;
}

function get_private_ip_address(): ?string
{
    return is_private_connection() ? ($_POST['private_ip_address'] ?? null) : null;
}

// Separator

function get_session_account_id()
{
    return is_private_connection() ? $_POST['session_account_id'] ?? null : null;
}

function set_session_account_id($id)
{
    $_POST['session_account_id'] = $id;
}

function has_session_account_id(): bool
{
    return !empty(get_session_account_id());
}