<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\FacebookClient;

Auth::requireLogin();

$state = bin2hex(random_bytes(16));
$_SESSION['fb_oauth_state'] = $state;

$client = new FacebookClient();
header('Location: ' . $client->loginUrl($state));
exit;
