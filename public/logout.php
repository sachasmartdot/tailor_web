<?php

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Model\User;

User::logout();

header('Location: index.php');