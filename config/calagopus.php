<?php

// Email templates
Configure::set('Calagopus.email_templates', [
    'en_us' => [
        'lang' => 'en_us',
        'text' => 'Thank you for ordering your game server!

Server Name: {service.server_name}
Server IP and Port: {service.server_ip}:{service.server_port}

Log into your account to start and manage your server through the Calagopus panel.',
        'html' => '<p>Thank you for ordering your game server!</p>
<p>Server Name: {service.server_name}<br />Server IP and Port: {service.server_ip}:{service.server_port}</p>
<p>Log into your account to start and manage your server through the Calagopus panel.</p>'
    ]
]);
