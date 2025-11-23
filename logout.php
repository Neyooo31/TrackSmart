<!doctype html>
<html lang="en">
    
</html>
<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
