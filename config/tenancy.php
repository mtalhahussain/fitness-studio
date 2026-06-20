<?php

$centralDomains = array_values(array_filter(array_map(
    fn ($d) => strtolower(trim($d)),
    explode(',', (string) env('CENTRAL_DOMAINS', 'localhost,127.0.0.1'))
)));

$appHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
if ($appHost && ! in_array(strtolower($appHost), $centralDomains, true)) {
    $centralDomains[] = strtolower($appHost);
}

return [
    // Hosts that should behave as central app domains (not tenant domains).
    'central_domains' => $centralDomains,

    // Base domain used for subdomain tenancy, e.g. app.com -> gym1.app.com
    'base_domain' => strtolower((string) env('APP_BASE_DOMAIN', '')),
];
