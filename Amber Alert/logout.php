<?php
session_start();
session_destroy();
header("Location: /Amber Alert/login.php");
exit();
?> 