<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/utils/Mailer.php';

$result = Mailer::send(
    'javigarcilop@gmail.com',
    'Prueba SMTP TempliMail',
    '<h1>Si lees esto, el SMTP funciona</h1>'
);

var_dump($result);
