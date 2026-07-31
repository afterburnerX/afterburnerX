<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;

Auth::logout();
header('Location: /login.php');
exit;
