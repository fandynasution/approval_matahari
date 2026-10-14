<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

trait SendsApprovalMail
{
    /**
     * Kirim email approval dengan proteksi double send (cache file + lock)
     * dan log untuk setiap hasil (mulai, berhasil, skip, gagal).
     *
     * clarify_email & sender_addr yang kosong / tidak valid (mis. "no email")
     * tidak menggagalkan pengiriman, hanya link mailto-nya yang dihilangkan.
     *
     * @param  string  $label      Nama modul untuk log, mis. 'CB RPB'
     * @param  string  $cacheDir   Folder di storage/app/mail_cache, mis. 'send_cbrpb'
     * @param  string  $mailClass  Class Mailable
     * @param  array   $meta       email, entity_cd, doc_no, level_no, approve_seq, entity_name, cache_key (array)
     * @param  array   $dataArray  Data untuk view email
     * @param  array   $data2Encrypt  Data yang dienkripsi untuk link approval
     * @param  array   $cc         Map email tujuan => alamat CC
     */
    protected function sendApprovalMail(string $label, string $cacheDir, string $mailClass, array $meta, array $dataArray, array $data2Encrypt, array $cc = []): string
    {
        $email    = strtolower(trim((string) ($meta['email'] ?? '')));
        $docNo    = trim((string) ($meta['doc_no'] ?? ''));
        $entityCd = trim((string) ($meta['entity_cd'] ?? ''));

        $log = $this->approvalLog(preg_replace('/Controller$/', '', class_basename(static::class)));
        $prefix = 'Email ' . $label . ' doc_no ' . $docNo . ' Entity ' . $entityCd;
        $context = [
            'entity_cd'     => $entityCd,
            'doc_no'        => $docNo,
            'level_no'      => $meta['level_no'] ?? null,
            'approve_seq'   => $meta['approve_seq'] ?? null,
            'email_addr'    => $email,
            'clarify_email' => $dataArray['clarify_email'] ?? null,
            'sender_addr'   => $dataArray['sender_addr'] ?? null,
        ];

        $log->info($prefix . ' - proses dimulai', $context);

        try {
            if ($email === '') {
                $log->warning($prefix . ' - GAGAL: email_addr penerima kosong', $context);
                return 'No email address provided';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $log->warning($prefix . ' - GAGAL: email_addr penerima tidak valid: ' . $email, $context);
                return 'Gagal mengirim email: alamat email tidak valid (' . $email . ')';
            }

            foreach (['clarify_email', 'sender_addr'] as $key) {
                $dataArray[$key] = $this->validEmailOrNull($dataArray[$key] ?? null);
                if ($dataArray[$key] === null) {
                    $log->notice($prefix . ' - ' . $key . ' kosong/tidak valid, email tetap dikirim tanpa link ' . $key, $context);
                }
            }

            // Samakan jumlah file_name dengan url_file agar view tidak error undefined index
            if (isset($dataArray['url_file'], $dataArray['file_name']) && is_array($dataArray['url_file']) && is_array($dataArray['file_name'])) {
                $dataArray['file_name'] = array_pad($dataArray['file_name'], count($dataArray['url_file']), '');
            }

            $mailable = new $mailClass(Crypt::encrypt($data2Encrypt), $dataArray, 'IFCA SOFTWARE - ' . ($meta['entity_name'] ?? ''));
        } catch (\Throwable $e) {
            return $this->logMailException($log, $prefix, $context, $e);
        }

        $cachePath = $this->mailCachePath($cacheDir, date('Ymd'), 'email_sent_', $meta['cache_key'] ?? []);

        return $this->deliverMail($log, $prefix, $context, [$email], !empty($cc[$email]) ? [$cc[$email]] : [], $cachePath, $mailable);
    }

    /**
     * Kirim email feedback (hasil approve / revise / cancel) ke staff.
     *
     * @param  string  $logModule  Nama file log, mis. 'FeedbackPoOrder'
     * @param  string  $label      Label di log, mis. 'Feedback Approval'
     * @param  string  $cacheDir   Folder di storage/app/mail_cache, mis. 'feedbackPoOrder'
     * @param  object  $mailable   Instance Mailable
     * @param  array   $meta       to, cc (opsional), doc_no, entity_cd, status, approve_seq, cache_date (opsional)
     */
    protected function sendFeedbackMail(string $logModule, string $label, string $cacheDir, $mailable, array $meta): string
    {
        $docNo    = trim((string) ($meta['doc_no'] ?? ''));
        $entityCd = trim((string) ($meta['entity_cd'] ?? ''));

        $log = $this->approvalLog($logModule);
        $prefix = 'Email ' . $label . ' doc_no ' . $docNo . ' Entity ' . $entityCd;
        $context = [
            'entity_cd'   => $entityCd,
            'doc_no'      => $docNo,
            'status'      => $meta['status'] ?? null,
            'approve_seq' => $meta['approve_seq'] ?? null,
            'email_addr'  => $meta['to'] ?? null,
            'email_cc'    => $meta['cc'] ?? null,
        ];

        $log->info($prefix . ' - proses dimulai', $context);

        try {
            [$to, $invalidTo] = $this->parseEmailList($meta['to'] ?? null);
            [$cc, $invalidCc] = $this->parseEmailList($meta['cc'] ?? null);
            $cc = array_values(array_diff($cc, $to));

            if ($invalidTo || $invalidCc) {
                $log->notice($prefix . ' - alamat tidak valid diabaikan: ' . implode(', ', array_merge($invalidTo, $invalidCc)), $context);
            }

            if (!$to) {
                $log->warning($prefix . ' - GAGAL: tidak ada alamat email penerima yang valid', $context);
                return 'Tidak ada alamat email untuk feedback yang diberikan';
            }

            $cacheDate = date('Ymd');
            if (!empty($meta['cache_date'])) {
                $parsed = $this->parseApprovedDate($meta['cache_date']);
                if ($parsed === null) {
                    $log->notice($prefix . ' - format tanggal approve tidak dikenali (' . $meta['cache_date'] . '), pakai tanggal hari ini', $context);
                } else {
                    $cacheDate = $parsed;
                }
            }
        } catch (\Throwable $e) {
            return $this->logMailException($log, $prefix, $context, $e);
        }

        $cachePath = $this->mailCachePath($cacheDir, $cacheDate, 'email_feedback_sent_', [
            $meta['approve_seq'] ?? '', $entityCd, $docNo, $meta['status'] ?? '',
        ]);

        return $this->deliverMail($log, $prefix, $context, $to, $cc, $cachePath, $mailable);
    }

    /**
     * Inti pengiriman: lock + cek cache (anti double send), kirim, tandai terkirim, log hasil.
     */
    protected function deliverMail($log, string $prefix, array $context, array $to, array $cc, string $cacheFilePath, $mailable): string
    {
        $lockHandle = null;
        $sentTo = implode(', ', $to) . ($cc ? ' & CC ke: ' . implode(', ', $cc) : '');

        try {
            $cacheDirectory = dirname($cacheFilePath);
            if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0755, true) && !is_dir($cacheDirectory)) {
                throw new \RuntimeException('Gagal membuat direktori cache: ' . $cacheDirectory);
            }

            // Exclusive lock agar tidak terjadi double send
            $lockHandle = fopen($cacheFilePath . '.lock', 'c');
            if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException('Gagal mendapatkan lock file');
            }

            if (file_exists($cacheFilePath)) {
                $log->info($prefix . ' - SKIP: sudah pernah dikirim ke: ' . $sentTo, $context);
                return 'Email has already been sent to: ' . $sentTo;
            }

            $mail = Mail::to($to);
            if ($cc) {
                $mail->cc($cc);
            }
            $sent = $mail->bcc('noreply@agungintiland.com')->send($mailable);

            file_put_contents($cacheFilePath, 'sent');

            $log->info($prefix . ' - BERHASIL dikirim ke: ' . $sentTo, $context + ['smtp' => $this->smtpSentDetail($sent)]);
            return 'Email berhasil dikirim ke: ' . $sentTo;
        } catch (\Throwable $e) {
            return $this->logMailException($log, $prefix, $context, $e);
        } finally {
            if (is_resource($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
    }

    /**
     * Detail email yang sudah diterima SMTP server: penerima sebenarnya (envelope,
     * termasuk BCC), Message-ID, dan respon server per perintah (MAIL FROM, RCPT TO, akhir DATA).
     * Tidak pernah melempar exception agar email yang sudah terkirim tidak dianggap gagal.
     */
    protected function smtpSentDetail($sent): array
    {
        if ($sent === null) {
            return ['status' => 'tidak ada SentMessage (pengiriman dibatalkan listener / mailer bukan SMTP)'];
        }

        try {
            $message = method_exists($sent, 'getOriginalMessage') ? $sent->getOriginalMessage() : null;
            $list = function ($addresses) {
                return array_map(fn($a) => $a->getAddress(), (array) $addresses);
            };

            $detail = [
                'message_id' => $sent->getMessageId(),
                'envelope'   => [
                    'from'       => $sent->getEnvelope()->getSender()->getAddress(),
                    'recipients' => $list($sent->getEnvelope()->getRecipients()),
                ],
            ];

            if ($message instanceof \Symfony\Component\Mime\Email) {
                $detail['header'] = [
                    'to'  => $list($message->getTo()),
                    'cc'  => $list($message->getCc()),
                    'bcc' => $list($message->getBcc()),
                ];
            }

            $debug = (string) $sent->getDebug();
            $detail['respon'] = $this->parseSmtpResponses($debug);
            $detail['transcript'] = $this->redactSmtpDebug($debug);

            return $detail;
        } catch (\Throwable $e) {
            return ['status' => 'gagal membaca detail SMTP: ' . $e->getMessage()];
        }
    }

    /**
     * Ambil respon server untuk MAIL FROM, tiap RCPT TO, dan akhir DATA dari transkrip SMTP.
     */
    protected function parseSmtpResponses(string $debug): array
    {
        $result = ['mail_from' => null, 'rcpt_to' => [], 'data' => null];
        $lastCmd = null;
        $lastArg = null;
        $afterData = false;

        foreach (preg_split('/\r?\n/', $debug) as $line) {
            $line = preg_replace('/^\[[^\]]*\]\s*/', '', trim($line));

            if (preg_match('/^>\s*(MAIL FROM|RCPT TO|DATA)\b:?\s*(.*)$/i', $line, $m)) {
                $lastCmd = strtoupper($m[1]);
                $lastArg = trim($m[2], " <>");
                continue;
            }

            if (!preg_match('/^<\s*(\d{3}.*)$/', $line, $m)) {
                continue;
            }
            $response = trim($m[1]);

            if ($lastCmd === 'MAIL FROM') {
                $result['mail_from'] = $response;
            } elseif ($lastCmd === 'RCPT TO') {
                $result['rcpt_to'][$lastArg] = $response;
            } elseif ($lastCmd === 'DATA' && strpos($response, '354') === 0) {
                $afterData = true;
            } elseif ($afterData) {
                // Respon setelah isi email dikirim, mis. "250 2.0.0 Ok: queued as ABC123"
                $result['data'] = $response;
            }
            $lastCmd = null;
        }

        return $result;
    }

    protected function logMailException($log, string $prefix, array $context, \Throwable $e): string
    {
        if ($e instanceof TransportExceptionInterface) {
            $diagnosis = $this->smtpFailureDetail($e);
            $log->error($prefix . ' - GAGAL SMTP [' . $diagnosis['kategori'] . ']: ' . $e->getMessage(), $context + $diagnosis);
            return 'Gagal mengirim email: [SMTP ' . $diagnosis['kategori'] . '] ' . $e->getMessage();
        }

        $log->error($prefix . ' - GAGAL mengirim email: ' . $e->getMessage(), $context + [
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
        ]);
        return 'Gagal mengirim email: ' . $e->getMessage();
    }

    protected function mailCachePath(string $cacheDir, string $date, string $filePrefix, array $keyParts): string
    {
        return storage_path('app/mail_cache/' . $cacheDir . '/' . $date . '/' . $filePrefix . implode('_', $keyParts) . '.txt');
    }

    /**
     * Pecah daftar email (dipisah ; atau ,) menjadi [valid, tidak valid].
     */
    protected function parseEmailList($value): array
    {
        $valid = [];
        $invalid = [];
        foreach (preg_split('/[;,]/', strtolower((string) $value)) as $address) {
            $address = trim($address);
            if ($address === '') {
                continue;
            }
            if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $address;
            } else {
                $invalid[] = $address;
            }
        }

        return [array_values(array_unique($valid)), $invalid];
    }

    /**
     * Tanggal approve dari SQL Server (mis. "Oct 13 2026 02:55PM") ke Ymd, null jika tidak dikenali.
     */
    protected function parseApprovedDate($value): ?string
    {
        $value = trim((string) $value);
        foreach (['M  j Y h:iA', 'M j Y h:iA', 'Y-m-d H:i:s', 'Y-m-d H:i:s.v', 'Y-m-d'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            if ($date !== false) {
                return $date->format('Ymd');
            }
        }

        return null;
    }

    /**
     * Detail kegagalan koneksi / pengiriman ke SMTP server untuk log:
     * kategori + saran, konfigurasi SMTP (tanpa password), rantai exception,
     * transkrip SMTP (kredensial disamarkan) dan hasil tes DNS & TCP.
     */
    protected function smtpFailureDetail(\Throwable $e): array
    {
        $mailer = config('mail.default');
        $cfg = config('mail.mailers.' . $mailer, []);
        $host = $cfg['host'] ?? null;
        $port = $cfg['port'] ?? null;

        // Rantai exception (penyebab asli biasanya ada di previous)
        $chain = [];
        $messages = '';
        for ($ex = $e; $ex; $ex = $ex->getPrevious()) {
            $chain[] = get_class($ex) . ' (code ' . $ex->getCode() . '): ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine();
            $messages .= ' ' . $ex->getMessage();
        }

        [$kategori, $saran] = $this->classifySmtpError($messages, (int) $e->getCode());

        return [
            'kategori'    => $kategori,
            'saran'       => $saran,
            'smtp_code'   => $e->getCode() ?: null,
            'smtp_config' => [
                'mailer'       => $mailer,
                'transport'    => $cfg['transport'] ?? null,
                'scheme'       => $cfg['scheme'] ?? $cfg['encryption'] ?? null,
                'host'         => $host,
                'port'         => $port,
                'username'     => $cfg['username'] ?? null,
                'password_set' => !empty($cfg['password']),
                'timeout'      => $cfg['timeout'] ?? null,
                'local_domain' => $cfg['local_domain'] ?? null,
                'from'         => config('mail.from.address'),
            ],
            'exception_chain' => $chain,
            'smtp_transcript' => $this->redactSmtpDebug(method_exists($e, 'getDebug') ? (string) $e->getDebug() : ''),
            'diagnosa'        => $this->probeSmtpServer($host, $port),
            'server'          => [
                'hostname'    => gethostname(),
                'php'         => PHP_VERSION,
                'openssl'     => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'tidak tersedia',
                'waktu'       => date('Y-m-d H:i:s'),
            ],
        ];
    }

    protected function classifySmtpError(string $message, int $code): array
    {
        $m = strtolower($message);

        if (preg_match('/getaddrinfo|php_network_getaddresses|no such host|name or service not known|could not resolve/', $m)) {
            return ['DNS_GAGAL', 'Hostname SMTP tidak bisa di-resolve. Cek MAIL_HOST dan DNS server.'];
        }
        if (preg_match('/connection refused|actively refused|10061/', $m)) {
            return ['KONEKSI_DITOLAK', 'Server SMTP menolak koneksi. Cek MAIL_PORT, service SMTP berjalan, dan firewall server tujuan.'];
        }
        if (preg_match('/timed out|timeout|10060|operation now in progress/', $m)) {
            return ['TIMEOUT', 'Koneksi ke SMTP timeout. Kemungkinan port diblok firewall/ISP atau server SMTP tidak merespon.'];
        }
        if (preg_match('/unreachable|no route to host|10065|10051/', $m)) {
            return ['JARINGAN_TIDAK_TERJANGKAU', 'Host SMTP tidak terjangkau dari server ini. Cek jaringan / routing / VPN.'];
        }
        if (preg_match('/ssl|tls|certificate|crypto|handshake|starttls/', $m)) {
            return ['SSL_TLS', 'Gagal negosiasi SSL/TLS. Cek MAIL_SCHEME (smtps untuk 465, smtp+STARTTLS untuk 587) dan sertifikat server.'];
        }
        if (in_array($code, [530, 534, 535, 538], true) || preg_match('/authenticat|username and password|invalid credentials|auth/', $m)) {
            return ['AUTENTIKASI_GAGAL', 'Login SMTP gagal. Cek MAIL_USERNAME / MAIL_PASSWORD atau kebijakan akun (app password, akun terkunci).'];
        }
        if (in_array($code, [550, 551, 553], true) || preg_match('/recipient|mailbox unavailable|user unknown|relay/', $m)) {
            return ['PENERIMA_DITOLAK', 'Alamat penerima/pengirim ditolak server SMTP (mailbox tidak ada atau relay tidak diizinkan).'];
        }
        if (in_array($code, [552, 554], true) || preg_match('/spam|blocked|blacklist|policy|rejected/', $m)) {
            return ['DITOLAK_KEBIJAKAN', 'Email ditolak oleh kebijakan server (spam/blacklist/ukuran). Cek reputasi IP dan ukuran lampiran.'];
        }
        if (in_array($code, [421, 450, 451, 452], true)) {
            return ['SERVER_SIBUK_SEMENTARA', 'Server SMTP menolak sementara (sibuk/rate limit). Coba kirim ulang beberapa saat lagi.'];
        }
        if (preg_match('/connection could not be established|unable to connect|failed to open stream/', $m)) {
            return ['KONEKSI_GAGAL', 'Tidak bisa membuka koneksi ke SMTP. Lihat hasil diagnosa DNS/TCP pada log.'];
        }
        if (preg_match('/expected response code|unexpected response/', $m)) {
            return ['RESPON_SMTP_TIDAK_SESUAI', 'Server SMTP memberi respon yang tidak diharapkan. Lihat smtp_transcript.'];
        }

        return ['SMTP_LAINNYA', 'Lihat exception_chain dan smtp_transcript untuk detail.'];
    }

    /**
     * Samarkan kredensial (AUTH) pada transkrip SMTP dan batasi panjangnya.
     */
    protected function redactSmtpDebug(string $debug): ?string
    {
        if (trim($debug) === '') {
            return null;
        }

        $commands = ['EHLO', 'HELO', 'MAIL', 'RCPT', 'DATA', 'QUIT', 'STARTTLS', 'RSET', 'NOOP', 'AUTH', '.'];
        $lines = preg_split('/\r?\n/', $debug);
        foreach ($lines as &$line) {
            if (preg_match('/^(\[[^\]]*\]\s*)?>\s*(\S*)(.*)$/', $line, $m)) {
                $cmd = strtoupper($m[2]);
                if ($cmd === 'AUTH') {
                    $parts = preg_split('/\s+/', trim($m[3]));
                    $line = $m[1] . '> AUTH ' . ($parts[0] ?? '') . (count($parts) > 1 ? ' [REDACTED]' : '');
                } elseif (!in_array($cmd, $commands, true) && strpos($cmd, ':') === false) {
                    $line = $m[1] . '> [REDACTED]';
                }
            }
        }
        unset($line);

        $debug = implode("\n", $lines);
        return strlen($debug) > 8000 ? '...' . substr($debug, -8000) : $debug;
    }

    /**
     * Tes DNS & koneksi TCP ke SMTP server untuk membantu analisa.
     */
    protected function probeSmtpServer($host, $port): array
    {
        if (empty($host) || empty($port)) {
            return ['status' => 'dilewati', 'alasan' => 'host/port SMTP tidak dikonfigurasi'];
        }

        $result = ['host' => $host, 'port' => (int) $port];

        $dnsStart = microtime(true);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        $result['dns'] = [
            'ok'      => !empty($ips),
            'ip'      => $ips,
            'durasi_ms' => (int) round((microtime(true) - $dnsStart) * 1000),
        ];

        $tcpStart = microtime(true);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, (int) $port, $errno, $errstr, 5);
        $result['tcp'] = [
            'ok'        => $socket !== false,
            'durasi_ms' => (int) round((microtime(true) - $tcpStart) * 1000),
            'errno'     => $errno ?: null,
            'error'     => $errstr ?: null,
        ];

        if ($socket !== false) {
            stream_set_timeout($socket, 5);
            $banner = fgets($socket, 512);
            $result['tcp']['banner'] = $banner !== false ? trim($banner) : 'tidak ada banner (timeout)';
            fclose($socket);
        }

        return $result;
    }

    /**
     * Logger per tanggal & modul: storage/logs/sendmail/{Ymd}/{Module}.log
     */
    protected function approvalLog(string $module)
    {
        $module = preg_replace('/[^A-Za-z0-9_-]/', '', $module) ?: 'Unknown';

        return Log::build([
            'driver' => 'single',
            'path'   => storage_path('logs/sendmail/' . date('Ymd') . '/' . $module . '.log'),
            'level'  => 'debug',
        ]);
    }

    protected function validEmailOrNull($value): ?string
    {
        $value = strtolower(trim((string) $value));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }
}
