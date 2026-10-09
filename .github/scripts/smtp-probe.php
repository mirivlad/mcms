<?php
// Disposable SMTP receiver for CI. Bind only the application container's loopback.
// No relay, authentication or delivery to external recipients.
$path = $argv[1];
$server = stream_socket_server('tcp://127.0.0.1:2526', $errno, $error);
if ($server === false) { fwrite(STDERR, $error); exit(1); }
echo "ready\n";
while (true) {
    clearstatcache();
    if (is_file($path . '.stop')) { break; }
    $connection = @stream_socket_accept($server, 1);
    if ($connection === false) { continue; }
    fwrite($connection, "220 mcms-probe ESMTP\r\n");
    $recipient = '';
    while (($line = fgets($connection)) !== false) {
        $command = strtoupper(strtok(trim($line), ' '));
        if ($command === 'EHLO' || $command === 'HELO') {
            fwrite($connection, "250 mcms-probe\r\n");
        } elseif ($command === 'RCPT') {
            $recipient = trim(substr($line, 8));
            fwrite($connection, "250 OK\r\n");
        } elseif ($command === 'DATA') {
            fwrite($connection, "354 End with dot\r\n");
            $message = '';
            while (($part = fgets($connection)) !== false && $part !== ".\r\n") {
                $message .= str_starts_with($part, '..') ? substr($part, 1) : $part;
            }
            clearstatcache();
            if (is_file($path . '.fail')) {
                fwrite($connection, "451 Retry later\r\n");
            } else {
                file_put_contents($path, json_encode(['recipient' => $recipient, 'message' => $message]) . "\n", FILE_APPEND | LOCK_EX);
                fwrite($connection, "250 Accepted\r\n");
            }
        } elseif ($command === 'QUIT') {
            fwrite($connection, "221 Bye\r\n");
            break;
        } else {
            fwrite($connection, "250 OK\r\n");
        }
    }
    fclose($connection);
}
