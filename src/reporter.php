<?php

function getEnvVar(string $name) {
    return getenv($name, true) ?: getenv($name);
}

function makeRequest(
    string $url,
    string $method,
    string $authorization,
    string $contentType,
    array $content
) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL             => $url,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_CUSTOMREQUEST   => $method,
        CURLOPT_HTTPHEADER      => [
            'Content-Type: ' . $contentType,
            'Authorization: ' . $authorization,
        ],
        CURLOPT_POSTFIELDS      => $contentType === 'application/json' ? json_encode($content) : $content
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);

    curl_close($curl);

    return compact('response', 'error');
}

$config = [
    'ipcheck_url'            => 'https://icanhazip.com',
    'ip_db_file'             => getEnvVar('SQLITE_DB_FILENAME'),
    'cloudflare_api_token'   => getEnvVar('CLOUDFLARE_API_TOKEN'),
    'cloudflare_email'       => getEnvVar('CLOUDFLARE_EMAIL'),
    'cloudflare_zone_id'     => getEnvVar('CLOUDFLARE_ZONE_ID'),
    'cloudflare_domain_id'   => getEnvVar('CLOUDFLARE_DOMAIN_ID'),
    'cloudflare_domain_name' => getEnvVar('CLOUDFLARE_DOMAIN_NAME'),
    'mailgun_api_key'        => getEnvVar('MAILGUN_API_KEY'),
    'mailgun_domain'         => getEnvVar('MAILGUN_DOMAIN'),
    'report_from_email'      => getEnvVar('REPORT_FROM_EMAIL'),
    'report_to_email'        => getEnvVar('REPORT_TO_EMAIL')
];

try {
    $db = new PDO('sqlite:' . __DIR__ . '/sqlite/' . $config['ip_db_file']);
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

    $ip = trim(file_get_contents($config['ipcheck_url']));

    if ($ip === '') exit;

    // If the IP checked different from the current IP logged (to file), update
    // CloudFlare DNS record for and send and email notification
    if ($currentIp === false || $currentIp['ip'] !== $ip) {
        // Add IP to database
        $db->prepare('INSERT INTO ip (ip, check_count, added_at, last_updated) VALUES (:ip, 1, :aa, :lu)')
            ->execute([
                ':ip' => $ip,
                ':aa' => date('Y-m-d H:i:s'),
                ':lu' => date('Y-m-d H:i:s')
            ]);

        // Update CloudFlare DNS using API call
        $cfRes = makeRequest(
            "https://api.cloudflare.com/client/v4/zones/{$config['cloudflare_zone_id']}/dns_records/{$config['cloudflare_domain_id']}",
            'PATCH',
            "Bearer {$config['cloudflare_api_token']}",
            'application/json',
            [
                'content' => $ip,
                'type'    => 'A',
                'name'    => $config['cloudflare_domain_name'],
                'ttl'     => 1, // Automatic
            ]
        );

        $responseObj = json_decode($cfRes['response']);

        if (!$responseObj->success) {
            throw new Exception("Failed to update CloudFlare DNS record! Message: {$responseObj->errors[0]->message}");
        }

        // Send email using Mailgun
        $oldIp = $currentIp === false ? 'NONE' : $currentIp['ip'];
        $mgPayload = array(
            'from'    => $config['report_from_email'],
            'to'      => $config['report_to_email'],
            'subject' => 'Home IP has changed',
            'text'    => "New IP: {$ip}\nOld IP: {$oldIp}"
        );

        $mgRes = makeRequest(
            "https://api.mailgun.net/v3/{$config['mailgun_domain']}/messages",
            'POST',
            'Basic ' . base64_encode("api:{$config['mailgun_api_key']}"),
            'multipart/form-data',
            $mgPayload
        );

        if ($mgRes['error']) {
            throw new Exception("Failed to send email with Mailgun! Message: {$mgRes['error']}");
        }
    } else {
        // Update check count, but only at the hour
        if (date('i') === '00') {
            $db->prepare('UPDATE ip SET check_count = :cc, last_updated = :lu WHERE id = :id')
                ->execute([
                ':cc' => $currentIp['check_count'] + 1,
                ':lu' => date('Y-m-d H:i:s'),
                ':id' => intval($currentIp['id'])
            ]);
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
    return 1;
} finally {
    if (isset($db) && $db !== null) {
        $db = null;
    }
}
