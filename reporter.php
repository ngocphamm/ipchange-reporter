<?php

require 'vendor/autoload.php';
require 'config.php';

use Mailgun\Mailgun;

try {
    $config = loadConfig();

    $handle = fopen('ip.txt', 'c+');
    if ($handle === false) return 1;

    $currentIp = trim(stream_get_contents($handle));
    $ip = trim(file_get_contents('http://i.ngx.cc'));

    // If the IP checked different from the current IP logged (to file). Send
    // notification
    if ($currentIp !== $ip) {
        fwrite($handle, $ip);

        if ($currentIp !== '') {
            $mg = new Mailgun($config['mailgun_api_key']);

            $mg->sendMessage(
                $config['mailgun_domain'],
                [
                    'from'    => $config['report_from_email'],
                    'to'      => $config['report_to_email'],
                    'subject' => 'Home IP has changed',
                    'text'    => 'Ngoc, your new IP is ' . $ip
                ]
            );
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

