<?php

include __DIR__ . '/vendor/autoload.php';

use NewTwitchApi\HelixGuzzleClient;
use NewTwitchApi\NewTwitchApi;

$clientId = 'zd00moo5jivus70gtnp73umied9hro';
$clientSecret = 't008ujo58wvggb4db3aokdhahfywua';
$accessToken = 'lu92dimwzmi8ry5gfh70qlnqe2noup';

$twitchApiClient = new HelixGuzzleClient($clientId);

$twitchApi = new NewTwitchApi($twitchApiClient, $clientId, $clientSecret);

$response = $twitchApi->getUsersApi()->getUserByAccessToken($accessToken);