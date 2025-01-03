<?php

// Headers

header("Access-Control-Allow-Origin: *");

// Set version

$og_version = '1.1';

// Set domain

$domain = 'hostedfiles.net';

// CDN var

if(isset($_GET['cdn'])) {

    $domain = 'cdn.' . $domain;

}

unset($_GET['cdn']);

// Input var

$u = ltrim($_GET['u'], '/');

if (empty($u)) {

    throw new Exception("Missing required query parameter 'u'.");

}

unset($_GET['u']);

function ip_in_range($ip, $range)
{
    if (strpos($range, '/') == false) {
        $range .= '/32';
    }
    // $range is in IP/CIDR format eg 127.0.0.1/24
    list($range, $netmask) = explode('/', $range, 2);
    $range_decimal = ip2long($range);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~ $wildcard_decimal;

    return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
}

// Get ip

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

if(is_null($ip)) {

    throw new Exception('Missing server var REMOTE_ADDR');

}

// Cloudflare IP ranges

$cf_ips = array(
    '199.27.128.0/21',
    '173.245.48.0/20',
    '103.21.244.0/22',
    '103.22.200.0/22',
    '103.31.4.0/22',
    '141.101.64.0/18',
    '108.162.192.0/18',
    '190.93.240.0/20',
    '188.114.96.0/20',
    '197.234.240.0/22',
    '198.41.128.0/17',
    '162.158.0.0/15',
    '104.16.0.0/12',
);

// For each Cloudflare IP range...

foreach ($cf_ips as $cf_ip) {

    // If a Cloudflare IP...

    if (ip_in_range($ip, $cf_ip)) {

        // Get IP forwarded by Cloudflare

        $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : null;

        // If cloudflare IP does not exist...

        if(is_null($ip)) {

            // Throw exception
        
            throw new Exception('Missing server var HTTP_CF_CONNECTING_IP');
        
        }

        // Break loop

        break;

    }

}

// Get user agent

$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

if(is_null($user_agent)) {

    throw new Exception('Missing server var HTTP_USER_AGENT');

}

// Get referrer

$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;

// Prepare header array

$headers = [
    'X-Forwarded-For: ' . $ip,
    'X-OGAds-Mirrored: ' . $og_version,
];

// Add script filename to headers

if(isset($_SERVER['SCRIPT_FILENAME'])) {

    $headers[] = 'X-OGAds-Script-Filename: ' . basename($_SERVER['SCRIPT_FILENAME']);

};

// Set URL

$url = "https://$domain/$u?" . http_build_query($_GET);

// Start CURL

$ch = curl_init();

// Set CURL options

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => $user_agent,
    CURLOPT_REFERER        => $referrer,
    CURLOPT_HTTPHEADER     => $headers,
]);

// Execute request

$content = curl_exec($ch);

// Get the host and content type of the URL we were redirected to

$url_new = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Check for error

if ($content === false) {

    // Throw exception if error found

    throw new Exception(curl_error($ch));

}

// Close CURL

curl_close($ch);

// Check URL host...

if (parse_url($url_new, PHP_URL_HOST) === $domain) {
        
    // If internal

    if (!is_null($content_type)) {

        // Set content type header

        header("Content-Type: $content_type");

    }

    // Output contents

    echo $content;

} else {

    // If external; redirect

    header("Location: $url_new");

}
