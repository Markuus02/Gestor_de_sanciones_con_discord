<?php
session_start();
session_destroy();
setcookie('discord_user', '', time() - 3600, '/'); // Borrar cookie
header('Location: index.php');
exit;
