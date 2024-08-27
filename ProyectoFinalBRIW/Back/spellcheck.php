<?php
$word = $_GET['word'];

$aspellOutput = shell_exec("echo \"$word\" | aspell -a");
$lines = explode("\n", $aspellOutput);

$suggestions = [];
foreach ($lines as $line) {
    if (strpos($line, '*') === 0) {
        $suggestions[] = trim(substr($line, 2));
    }
}

header('Content-Type: application/json');
echo json_encode($suggestions);
?>
