<?php

// Create the SQLite database
// CREATE TABLE "ip"(id integer NOT NULL PRIMARY KEY AUTOINCREMENT, ip text not null, check_count integer not null, added_at text not null, last_updated text);

$config = [
    'cloudflare_api_key' => '',
    'cloudflare_email' => '',
    'cloudflare_zone_id' => '',
    'cloudflare_domain_id' => '',
    'cloudflare_domain_name' => '',
    'mailgun_api_key' => '',
    'mailgun_domain' => '',
    'report_from_email' => '',
    'report_to_email' => ''
];

return $config;
