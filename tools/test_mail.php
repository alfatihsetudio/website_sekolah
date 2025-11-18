<?php
require_once __DIR__ . '/../inc/mail_helper.php';

$res = send_password_email_smtp('email_tujuan_kamu@gmail.com', 'Nama Tes', 'tes12345');
var_dump($res);
echo "<br>Check storage/email_log.txt for details.";
