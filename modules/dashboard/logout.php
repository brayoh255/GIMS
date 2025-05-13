<?php
session_start();
session_unset();
session_destroy();
header("Location: ../modules/auth/login.php");

exit();
