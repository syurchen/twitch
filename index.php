#!/usr/bin/env php
<?php
/*

Copyright 2017 Amazon.com, Inc. or its affiliates. All Rights Reserved.

Licensed under the Apache License, Version 2.0 (the "License"). You may not use this file except in compliance with the License. A copy of the License is located at

    http://aws.amazon.com/apache2.0/

or in the "license" file accompanying this file. This file is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.

*/

require 'twitchLib.php';
include 'config.php';
include __DIR__ . '/vendor/autoload.php';

use NewTwitchApi\HelixGuzzleClient;
use NewTwitchApi\NewTwitchApi;
use LucidFrame\Console\ConsoleTable;

/*
// Params from your twitch app 
$CLID = ***;
$SECRET = ***;
// Your server should have script that echoes request in JSON
$REDIRECT_URI = ***; 

//User to get follows for
$userName = ***
 */

$chatW = 450;
$chatH = 700;
$chatX = 1700;
$chatY = 0;


$provider = new TwitchProvider([
    'clientId'                => $CLID,     // The client ID assigned when you created your application
    'clientSecret'            => $SECRET, // The client secret assigned when you created your application
    'redirectUri'             => $REDIRECT_URI,  // Your redirect URL you specified when you created your application
]);


// If we don't have an authorization code then get one

    // Fetch the authorization URL from the provider, and store state in session
$authorizationUrl = $provider->getAuthorizationUrl();
$_SESSION['oauth2state'] = $provider->getState();

try{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $authorizationUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
	curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
	$output = curl_exec($ch);
	curl_close($ch);
	$output = json_decode($output, true);
	$code = $output['code'];
} catch (Exception $e){
	print_r($e->getTraceAsString());
	echo "\nOAUTH:\n$authorizationUrl \n";

	exec("firefox '{$authorizationUrl}'");

	$code = readline("code pls:\n");
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.twitch.tv/kraken/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => $CLID,
    'client_secret' => $SECRET,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $REDIRECT_URI,
    'code' => $code
]));
$output = curl_exec($ch);
curl_close($ch);

$oauth = json_decode($output, true);

$twitchApiClient = new HelixGuzzleClient($CLID);

$twitchApi = new NewTwitchApi($twitchApiClient, $CLID, $SECRET);

$response = $twitchApi->getUsersApi()->getUserByAccessToken($oauth['access_token']);

$userApi = $twitchApi->getUsersApi();
$response = $userApi->getUserByUsername($userName);
$id = json_decode($response->getBody()->getContents(), true)['data'][0]['id'];

$following = [];
$cursor = null;
while (true){
	$response = $userApi->getUsersFollows($id, null, null, $cursor);
	$response = json_decode($response->getBody()->getContents(), true);
	$tempFollowing =  $response['data'];
	if(isset($response['pagination']['cursor']))
		$cursor = $response['pagination']['cursor'];
	if (empty($tempFollowing))
		break;
	$following = array_merge($following, $tempFollowing);
}

//$following = array_unique($following);

$fids = [];
$streams = [];
$streamApi = $twitchApi->getStreamsApi();

foreach ($following as $id => $follow){
	$fids[] = $follow['to_id'];
	if ($id && (!($id % 50) || $id == count($following) - 1)){
		$response = $streamApi->getStreams($fids);
		$streams = array_merge($streams, json_decode($response->getBody()->getContents(), true)['data']);
		$fids = [];
	}
}

usort($streams, function($a, $b){
	if (!isset($a['viewer_count']) || !isset($b['viewer_count'])){
		return false;
	}
	return $b['viewer_count'] - $a['viewer_count'];
});

$table = new ConsoleTable();
$table->addHeader('id')->addHeader('User')->addHeader('Title')->addHeader('Uptime');
foreach ($streams as $id => $stream){
	$uptime = gmdate("H:i:s", (time() - strtotime($stream['started_at'])));
	$table->addRow()
		->addColumn($id)
		->addColumn($stream['user_name'])
		->addColumn($stream['title'])
		->addColumn($stream['viewer_count'])
		->addColumn($uptime);
}

$table->display();

$streamId = trim(readline("What id to play?\n"));

if (strlen($streamId)){

	$selected = $streams[$streamId]['user_name'];

	//exec("firefox -new-window 'https://www.twitch.tv/popout/{$selected}/chat?popout='");
	$cmd = "firefox -url 'data:text/html;charset=utf-8,<!DOCTYPE html><html><body><script>window.open(\"https://www.twitch.tv/popout/{$selected}/chat?popout=\", \"_blank\",\"height={$chatH},width={$chatW},menubar=no,location=no,toolbar=no,left={$chatX},top={$chatY}\")<%2Fscript><%2Fbody><%2Fhtml>'";
	exec($cmd);
	exec("streamlink twitch.tv/{$selected} best");
}