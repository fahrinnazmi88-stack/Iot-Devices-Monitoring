<?php
require 'config.php';
if (!empty($_SESSION['user'])) audit($conn, 'logout');
session_destroy();
header('Location: login.php');