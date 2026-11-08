<?php
/**
 * Text-to-Speech (TTS) Helper
 * Menyediakan 3 engine TTS:
 * 1. Google Translate TTS (Online - Natural & Fasih Bahasa Indonesia)
 * 2. eSpeak-NG / Offline Linux STB Engine (100% Offline di STB)
 * 3. Browser Recorded Audio (Audio Blob hasil rekam Web Speech API di HP/Browser diupload ke STB)
 */

class TTSHelper {
    /**
     * Membersihkan dan membuat nama file aman dari teks
     *
     * @param string $text
     * @param string $prefix
     * @param string $ext
     * @return string
     */
    public static function generateFilename($text, $prefix = 'tts_', $ext = 'mp3') {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower(trim($text)));
        $slug = trim($slug, '_');
        if (strlen($slug) > 30) {
            $slug = substr($slug, 0, 30);
        }
        if (empty($slug)) {
            $slug = 'audio';
        }
        $hash = substr(md5($text . time()), 0, 5);
        return $prefix . $slug . '_' . $hash . '.' . $ext;
    }

    /**
     * Opsi 1: Google Translate TTS (Online)
     * Mengunduh audio MP3 berbahasa Indonesia langsung ke uploads/audio/
     *
     * @param string $text
     * @param string $lang Default 'id'
     * @return array ['success' => bool, 'filename' => string, 'message' => string]
     */
    public static function generateGoogleTTS($text, $lang = 'id') {
        $text = trim($text);
        if (empty($text)) {
            return ['success' => false, 'message' => 'Teks tidak boleh kosong'];
        }

        $audio_dir = __DIR__ . '/../uploads/audio';
        if (!is_dir($audio_dir)) {
            mkdir($audio_dir, 0777, true);
        }

        $filename = self::generateFilename($text, 'tts_online_', 'mp3');
        $filepath = $audio_dir . '/' . $filename;

        // Google Translate TTS URL
        $encodedText = urlencode($text);
        $url = "https://translate.google.com/translate_tts?ie=UTF-8&q={$encodedText}&tl={$lang}&client=tw-ob";

        $audioData = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $audioData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || empty($audioData)) {
                return ['success' => false, 'message' => "Gagal menghubungi server Google TTS (HTTP {$httpCode} - {$curlErr}). Periksa koneksi internet STB."];
            }
        } else {
            $audioData = @file_get_contents($url);
            if (empty($audioData)) {
                return ['success' => false, 'message' => 'Gagal mengunduh audio Google TTS. Pastikan koneksi internet aktif.'];
            }
        }

        if (file_put_contents($filepath, $audioData) !== false) {
            return [
                'success' => true,
                'filename' => $filename,
                'message' => 'Audio TTS berhasil dibuat (Google Online)'
            ];
        }

        return ['success' => false, 'message' => 'Gagal menulis file audio ke penyimpanan STB'];
    }

    /**
     * Opsi 2: Offline TTS di STB Linux (eSpeak-ng / Piper)
     * Menghasilkan file .wav secara 100% offline
     *
     * @param string $text
     * @param string $lang
     * @return array ['success' => bool, 'filename' => string, 'message' => string]
     */
    public static function generateOfflineTTS($text, $lang = 'id') {
        $text = trim($text);
        if (empty($text)) {
            return ['success' => false, 'message' => 'Teks tidak boleh kosong'];
        }

        $audio_dir = __DIR__ . '/../uploads/audio';
        if (!is_dir($audio_dir)) {
            mkdir($audio_dir, 0777, true);
        }

        $filename = self::generateFilename($text, 'tts_offline_', 'wav');
        $filepath = $audio_dir . '/' . $filename;
        $safePath = escapeshellarg($filepath);
        $safeText = escapeshellarg($text);

        // Cek apakah eSpeak-ng atau eSpeak terpasang di STB
        $has_espeak_ng = false;
        $has_espeak = false;
        $has_piper = false;

        if (function_exists('exec')) {
            $check_ng = @exec('which espeak-ng 2>/dev/null');
            $has_espeak_ng = !empty($check_ng);
            $check_es = @exec('which espeak 2>/dev/null');
            $has_espeak = !empty($check_es);
            $check_pi = @exec('which piper 2>/dev/null');
            $has_piper = !empty($check_pi);
        }
        if ($has_espeak_ng) {
            // eSpeak-NG dengan suara bahasa Indonesia (-v id)
            $cmd = "espeak-ng -v {$lang} -w {$safePath} {$safeText} 2>&1";
            exec($cmd, $out, $ret);
            if ($ret === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'message' => 'Audio TTS offline berhasil dibuat (eSpeak-NG)'
                ];
            }
        } elseif ($has_espeak) {
            // Fallback eSpeak lama
            $cmd = "espeak -v {$lang} -w {$safePath} {$safeText} 2>&1";
            exec($cmd, $out, $ret);
            if ($ret === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'message' => 'Audio TTS offline berhasil dibuat (eSpeak)'
                ];
            }
        }

        if ($has_piper) {
            $cmd = "echo {$safeText} | piper --output_file {$safePath} 2>&1";
            exec($cmd, $out, $ret);
            if ($ret === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'message' => 'Audio TTS offline berhasil dibuat (Piper TTS)'
                ];
            }
        }

        // Fallback untuk Windows (SAPI Speech Synthesis)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $psCode = "Add-Type -AssemblyName System.Speech; \$synth = New-Object System.Speech.Synthesis.SpeechSynthesizer; \$synth.SetOutputToWaveFile('{$filepath}'); \$synth.Speak('{$text}'); \$synth.Dispose();";
            $psCmd = "powershell -Command \"" . str_replace('"', '\"', $psCode) . "\"";
            exec($psCmd, $out, $ret);
            if (file_exists($filepath) && filesize($filepath) > 0) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'message' => 'Audio TTS offline berhasil dibuat (Windows SAPI)'
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Paket TTS offline (espeak-ng) belum terpasang di STB Linux. Jalankan: sudo apt-get install -y espeak-ng'
        ];
    }

    /**
     * Opsi 3: Menyimpan file audio rekaman hasil Web Speech API dari browser
     *
     * @param array $fileData Array $_FILES['audio_blob']
     * @param string $label
     * @return array
     */
    public static function saveBrowserTTSAudio($fileData, $label = '') {
        if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            return ['success' => false, 'message' => 'Tidak ada file audio rekaman yang diterima dari browser'];
        }

        $audio_dir = __DIR__ . '/../uploads/audio';
        if (!is_dir($audio_dir)) {
            mkdir($audio_dir, 0777, true);
        }

        $origExt = strtolower(pathinfo($fileData['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($origExt, ['wav', 'mp3', 'ogg', 'webm'])) {
            $origExt = 'wav';
        }

        $filename = self::generateFilename($label ?: 'browser', 'tts_browser_', $origExt);
        $target = $audio_dir . '/' . $filename;

        if (move_uploaded_file($fileData['tmp_name'], $target)) {
            return [
                'success' => true,
                'filename' => $filename,
                'message' => 'Audio TTS dari browser berhasil disimpan'
            ];
        }

        return ['success' => false, 'message' => 'Gagal memindahkan file audio rekaman'];
    }
}
