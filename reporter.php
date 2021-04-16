<?php

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Mailgun\Mailgun;
use Psr\Http\Message\ResponseInterface;

try {
    $config = require_once __DIR__ . '/config.php';

    $db = new PDO('sqlite:' . __DIR__ . '/ip.db3');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set some PRAGMA
    $db->exec('PRAGMA journal_mode = wal;');
    $db->exec('PRAGMA busy_timeout = 5000;');

    // Create table if needed
    $createTableSql = <<<SQL
        CREATE TABLE IF NOT EXISTS `ip` (
            `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `ip` TEXT NOT NULL,
            `check_count` INTEGER NOT NULL,
            `added_at` TEXT NOT NULL,
            `last_updated` TEXT
        );
    SQL;

    $db->exec($createTableSql);

    $stmt = $db->query('SELECT * FROM ip ORDER BY added_at DESC LIMIT 1');
    $stmt->execute();
    $currentIp = $stmt->fetch(PDO::FETCH_ASSOC);

    $ip = trim(file_get_contents('https://api.ipify.org'));

    if ($ip === '') exit;

    // If the IP checked different from the current IP logged (to file), update
    // CloudFlare DNS record for and send and email notification
    if ($currentIp === false || $currentIp['ip'] !== $ip) {
        // Add ip to database
        $db->prepare('INSERT INTO ip (ip, check_count, added_at, last_updated) VALUES (:ip, 1, :aa, :lu)')
            ->execute([ ':ip' => $ip, ':aa' => date('Y-m-d H:i:s'), ':lu' => date('Y-m-d H:i:s') ]);

        // Update CloudFlare DNS using API call
        $client = new Client([ 'base_uri' => 'https://api.cloudflare.com/client/v4/zones/' ]);
        $promise = $client->requestAsync('PUT', "{$config['cloudflare_zone_id']}/dns_records/{$config['cloudflare_domain_id']}", [
            'headers' => [
                'Authorization' => "Bearer {$config['cloudflare_api_token']}"
            ],
            'json' => [
                'content' => $ip,
                'type' => 'A',
                'name' => $config['cloudflare_domain_name'],
                'ttl'  => 1, // Automatic
            ]
        ])->then(
            function (ResponseInterface $res) {
                if ($res->getStatusCode() !== 200) {
                    throw new Exception("Attempt to update CloudFlare DNS record failed! Status code: {$res->getStatusCode()}");
                }
            },
            function (RequestException $e) {
                throw new Exception("Attempt to update CloudFlare DNS record failed! Message: {$e->getMessage()}");
            }
        )->wait();

        // Send email using Mailgun
        $mg = Mailgun::create($config['mailgun_api_key']);
        $oldIp = $currentIp === false ? 'NONE' : $currentIp['ip'];

        $mg->messages()->send($config['mailgun_domain'], [
            'from'    => $config['report_from_email'],
            'to'      => $config['report_to_email'],
            'subject' => 'Home IP has changed',
            'text'    => "New IP: {$ip}\nOld IP: {$oldIp}"
        ]);
    } else {
        // Update check count
        $db->prepare('UPDATE ip SET check_count = :cc, last_updated = :lu WHERE id = :id')
            ->execute([
                ':cc' => $currentIp['check_count'] + 1,
                ':lu' => date('Y-m-d H:i:s'),
                ':id' => intval($currentIp['id'])
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
