<?php
// Must be first — no output before redirect
session_start();
session_destroy();
header('Location: ../../index.php');
exit;
