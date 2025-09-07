<?php
// ! we dont want users to access this directory directly
http_response_code(403);
die('Access denied');
?>