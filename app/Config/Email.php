<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail = 'noreply@example.test';
    public string $fromName = 'mCMS';
    public string $recipients = '';
    public string $userAgent = 'mCMS';
    public string $protocol = 'smtp';
    public string $mailPath = '/usr/sbin/sendmail';
    public string $SMTPHost = 'mail';
    public string $SMTPAuthMethod = 'login';
    public string $SMTPUser = '';
    public string $SMTPPass = '';
    public int $SMTPPort = 587;
    public int $SMTPTimeout = 10;
    public bool $SMTPKeepAlive = false;
    public string $SMTPCrypto = '';
    public bool $wordWrap = true;
    public int $wrapChars = 76;
    public string $mailType = 'html';
    public string $charset = 'UTF-8';
    public bool $validate = false;
    public int $priority = 3;
    public string $CRLF = "\r\n";
    public string $newline = "\r\n";
    public bool $BCCBatchMode = false;
    public int $BCCBatchSize = 200;
    public bool $DSN = false;

    public function __construct()
    {
        parent::__construct();
        $this->fromEmail = getenv('MCMS_MAIL_FROM') ?: $this->fromEmail;
        $this->fromName = getenv('MCMS_MAIL_FROM_NAME') ?: $this->fromName;
        $this->SMTPHost = getenv('MCMS_MAIL_HOST') ?: $this->SMTPHost;
        $this->SMTPPort = (int) (getenv('MCMS_MAIL_PORT') ?: $this->SMTPPort);
        // Ordinary shared hosting uses the provider SMTP relay, not Docker's
        // private Postfix hostname. Credentials live only in the private .env.
        if (\MCMS\Core\Install\SharedHostSetup::enabled()) {
            $this->SMTPUser = (string) (getenv('MCMS_MAIL_USER') ?: '');
            $encoded = (string) (getenv('MCMS_MAIL_PASSWORD_B64') ?: '');
            $this->SMTPPass = $encoded !== '' ? (base64_decode($encoded, true) ?: '') : '';
            $crypto = strtolower((string) (getenv('MCMS_MAIL_CRYPTO') ?: 'tls'));
            $this->SMTPCrypto = in_array($crypto, ['', 'tls', 'ssl'], true) ? $crypto : 'tls';
        }
    }
}
