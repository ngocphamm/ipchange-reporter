<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Httpful\Request;
use Mailgun\Mailgun;

try {
    $config = loadConfig();

    $handle = fopen(__DIR__ . '/ip.txt', 'c+');
    if ($handle === false) return 1;

    $currentIp = trim(stream_get_contents($handle));
    $ip = trim(file_get_contents('https://i.ngx.cc'));

    // If the IP checked different from the current IP logged (to file), update
    // CloudFlare DNS record for and send and email notification
    if ($currentIp !== $ip) {
        ftruncate($handle, 0);
        fwrite($handle, $ip);

        if ($ip !== '') {
            // Update CloudFlare DNS using API call
            $cfPutData = [
                'content' => $ip,
                'type' => 'A',
                'name' => $config['cloudflare_domain_name']
            ];

            $response = Request::put("https://api.cloudflare.com/client/v4/zones/{$config['cloudflare_zone_id']}/dns_records/{$config['cloudflare_domain_id']}")
                ->sendsJson()
                ->addHeaders(array(
                    'X-Auth-Key' => $config['cloudflare_api_key'],
                    'X-Auth-Email' => $config['cloudflare_email']
                ))
                ->body(json_encode($cfPutData))
                ->send();

            if ($response->code !== 200) {
                throw new Exception('Attempt to update CloudFlare DNS record failed!');
            }

            // Send email using Mailgun
            $mg = Mailgun::create($config['mailgun_api_key']);

            $mg->messages()->send($config['mailgun_domain'], [
                'from'    => $config['report_from_email'],
                'to'      => $config['report_to_email'],
                'subject' => 'Home IP has changed',
                'text'    => 'Ngoc, your new IP is ' . $ip
            ]);
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
    return 1;
} finally {
    if ($handle !== null) {
        fclose($handle);
    }
}

