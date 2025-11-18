<?php
// inc/mail_helper.php
// Simple PHPMailer wrapper with verbose logging to storage/email_log.txt
// Returns array ['ok'=>bool,'msg'=>string,'debug'=>string]

if (!defined('BASE_DIR')) {
    // define BASE_DIR if belum ada (sesuaikan dengan project)
    define('BASE_DIR', realpath(__DIR__ . '/..'));
}

function email_log($line) {
    $d = date('[Y-m-d H:i:s] ');
    $logdir = BASE_DIR . '/storage';
    if (!is_dir($logdir)) @mkdir($logdir, 0777, true);
    $file = $logdir . '/email_log.txt';
    @file_put_contents($file, $d . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function load_smtp_config() {
    $cfgFile = __DIR__ . '/smtp_config.php';
    if (!file_exists($cfgFile)) {
        email_log("ERROR: smtp_config.php not found at $cfgFile");
        return null;
    }
    $cfg = include $cfgFile;
    if (!is_array($cfg)) {
        email_log("ERROR: smtp_config.php did not return array");
        return null;
    }
    return $cfg;
}

function ensure_autoload() {
    // try vendor/autoload.php relative to project root
    $paths = [
        __DIR__ . '/../vendor/autoload.php',      // typical
        __DIR__ . '/../../vendor/autoload.php',   // if inc is in subfolder
        __DIR__ . '/vendor/autoload.php'
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            return true;
        }
    }
    email_log("ERROR: composer autoload not found (checked: " . implode(', ', $paths) . ")");
    return false;
}

/**
 * send_password_email_smtp($toEmail, $toName, $plainPassword)
 * returns array ['ok'=>bool, 'msg'=>string, 'debug'=>string]
 */
function send_password_email_smtp($toEmail, $toName, $plainPassword) {
    email_log("ATTEMPT: send_password_email_smtp to={$toEmail} name={$toName}");

    if (!ensure_autoload()) {
        return ['ok'=>false, 'msg'=>'autoload_missing', 'debug'=>'composer autoload not found'];
    }

    $cfg = load_smtp_config();
    if (!$cfg) {
        return ['ok'=>false, 'msg'=>'smtp_config_missing', 'debug'=>'smtp config missing or invalid'];
    }

    // Validate required keys
    $required = ['host','port','username','password','secure','from_email','from_name'];
    foreach ($required as $k) {
        if (!isset($cfg[$k]) || $cfg[$k] === '') {
            email_log("ERROR: smtp_config key missing: $k");
            return ['ok'=>false, 'msg'=>'smtp_config_incomplete', 'debug'=>"missing $k"];
        }
    }

    // Build message (HTML + plain)
    $subject = "Akun sekolah — password sementara";
    $html = "<p>Halo " . htmlspecialchars($toName) . ",</p>";
    $html .= "<p>Akun Anda telah dibuat. Password sementara: <strong>" . htmlspecialchars($plainPassword) . "</strong></p>";
    $html .= "<p>Silakan masuk dan ganti password segera.</p>";
    $html .= "<p>Salam,<br/>" . htmlspecialchars($cfg['from_name']) . "</p>";
    $alt = "Halo {$toName}\nPassword sementara: {$plainPassword}\nSilakan ganti password setelah login.";

    // Use PHPMailer
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // Server settings
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->Port = (int)$cfg['port'];
        $mail->SMTPSecure = $cfg['secure']; // 'tls' or 'ssl'
        // Optional: debug level - DO NOT enable in production
        // $mail->SMTPDebug = 0;

        // From
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $alt;

        // Send
        $mail->send();
        email_log("SENT: to={$toEmail} ok");
        return ['ok'=>true, 'msg'=>'sent', 'debug'=>''];
    } catch (Exception $ex) {
        $err = "PHPMailer Exception: " . $ex->getMessage();
        $pmErr = property_exists($mail, 'ErrorInfo') ? $mail->ErrorInfo : '';
        email_log("FAILED: to={$toEmail} reason={$err} PHPMailerError={$pmErr}");
        return ['ok'=>false, 'msg'=>'send_failed', 'debug'=>$err . ' | PHPMailerError=' . $pmErr];
    } catch (\Throwable $t) {
        $err = "Throwable: " . $t->getMessage();
        email_log("FAILED: to={$toEmail} throwable={$err}");
        return ['ok'=>false, 'msg'=>'send_failed', 'debug'=>$err];
    }
}
