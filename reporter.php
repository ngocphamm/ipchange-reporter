<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Httpful\Request;
use Mailgun\Mailgun;

try {
    $config = loadConfig();

    $db = new PDO('sqlite:' . __DIR__ . '/ip.db3');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->query('SELECT * FROM ip ORDER BY added_at DESC LIMIT 1');
    $stmt->execute();
    $current_ip = $stmt->fetch(PDO::FETCH_ASSOC);

    $ip = trim(file_get_contents('https://i.ngx.cc'));

    if ($ip === '') exit;

    // If the IP checked different from the current IP logged (to file), update
    // CloudFlare DNS record for and send and email notification
    if ($current_ip === false || $current_ip['ip'] !== $ip) {
        // Add ip to database
        $stmt = $db->prepare('INSERT INTO ip (ip, added_at, check_count) VALUES (:ip, :dt, 1)')
                    ->execute([ ':ip' => $ip, ':dt' => date('Y-m-d H:i:s') ]);

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
            'text'    => 'Ngoc, your new IP is ' . $ip . ' Old IP: ' . $current_ip['ip']
        ]);
    } else {
        // Update check count
        $db->prepare('UPDATE ip SET check_count = :cc WHERE id = :id')
            ->execute([
                ':cc' => $current_ip['check_count'] + 1,
                ':id' => intval($current_ip['id'])
            ]);
    }
} catch (Exception $e) {
    echo $e->getMessage();
    return 1;
} finally {
    if (isset($db) && $db !== null) {
        $db = null;
    }
}

