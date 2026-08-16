<?php
require_once __DIR__ . '/../../includes/Session.php';
Session::logout();
app_redirect('login.php');
