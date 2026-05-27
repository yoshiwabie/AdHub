<?php
session_start();
session_destroy();

header("Location: /AdHub_V2/index.php");
exit();
?>