<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_admin();
$name=basename((string)($_GET['name']??''));
if($name===''||!preg_match('/^routebox-telegram-bot-v[0-9A-Za-z.-]+(?:-pre-update)?-[0-9]{8}-[0-9]{6}\.tar\.gz$/',$name)){http_response_code(404);exit('Not found');}
$file='/var/backups/routebox-telegram-bot/'.$name;
if(!is_file($file)){http_response_code(404);exit('Not found');}
header('Content-Type: application/gzip');header('Content-Length: '.filesize($file));header('Content-Disposition: attachment; filename="'.$name.'"');readfile($file);
