<?php
session_start();
unset($_SESSION['admin_logged_in'], $_SESSION['admin_user']);
header('Location: ' . BASE . '/admin');
exit;
