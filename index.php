<?php
require_once("functions.php");
session_start();

header('Content-Type: text/html; charset=utf-8');

$authUrl = getAuthorizationUrl("", "");
?>
<!DOCTYPE html>
<html lang="fi">
<head>
	<title>Google Drive Login and Upload</title>
	<meta charset="UTF-8">
</head>
<body>

<a href=<?php echo "'" . $authUrl . "'" ?>>Authorize</a>

</body>
</html>