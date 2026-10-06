<?php
/**
 * Script untuk membuat audio WAV sintetis nada bel standar
 * (Bel Pendek, Bel Masuk, Bel Istirahat, Bel Pulang, Sirine Darurat)
 */

$audio_dir = __DIR__ . '/../uploads/audio';
if (!is_dir($audio_dir)) {
    mkdir($audio_dir, 0777, true);
}

/**
 * Menghasilkan file audio WAV format PCM 16-bit Mono
 *
 * @param string $filepath Path file tujuan
 * @param array $notes Daftar nada: array of ['freq' => Hz, 'dur' => detik]
 * @param int $sampleRate Frekuensi sampling
 */
function create_chime_wav($filepath, $notes, $sampleRate = 22050) {
    $rawData = '';
    
    foreach ($notes as $note) {
        $freq = $note['freq'];
        $duration = $note['dur'];
        $numSamples = (int)($duration * $sampleRate);
        
        for ($i = 0; $i < $numSamples; $i++) {
            $t = $i / $sampleRate;
            $progress = $i / $numSamples;
            
            // Generate fundamental + harmonic
            $val = 0.7 * sin(2 * M_PI * $freq * $t) + 0.3 * sin(2 * M_PI * ($freq * 2) * $t);
            
            // Envelope: Fast attack, natural decay
            $decay = exp(-3.0 * $progress);
            $val *= $decay;
            
            $sample = (int)($val * 32767 * 0.9);
            $sample = max(-32768, min(32767, $sample));
            $rawData .= pack('v', $sample);
        }
    }

    $dataLen = strlen($rawData);
    $header = 'RIFF' . pack('V', 36 + $dataLen) .
              'WAVEfmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) .
              pack('V', $sampleRate) . pack('V', $sampleRate * 2) . pack('v', 2) . pack('v', 16) .
              'data' . pack('V', $dataLen);

    file_put_contents($filepath, $header . $rawData);
}

// 1. Chime Pendek (2 nada: Sol - Do tinggi)
create_chime_wav($audio_dir . '/chime-pendek.wav', [
    ['freq' => 523.25, 'dur' => 0.6], // C5
    ['freq' => 659.25, 'dur' => 1.0]  // E5
]);

// 2. Bel Masuk (3 nada: Do - Mi - Sol)
create_chime_wav($audio_dir . '/bel-masuk.wav', [
    ['freq' => 523.25, 'dur' => 0.7], // C5
    ['freq' => 659.25, 'dur' => 0.7], // E5
    ['freq' => 783.99, 'dur' => 1.5]  // G5
]);

// 3. Bel Istirahat (3 nada descending)
create_chime_wav($audio_dir . '/bel-istirahat.wav', [
    ['freq' => 783.99, 'dur' => 0.7], // G5
    ['freq' => 659.25, 'dur' => 0.7], // E5
    ['freq' => 523.25, 'dur' => 1.5]  // C5
]);

// 4. Bel Pulang (Westminster 4 nada: Mi - Do - Re - Sol rendah)
create_chime_wav($audio_dir . '/bel-pulang.wav', [
    ['freq' => 659.25, 'dur' => 0.8], // E5
    ['freq' => 523.25, 'dur' => 0.8], // C5
    ['freq' => 587.33, 'dur' => 0.8], // D5
    ['freq' => 392.00, 'dur' => 2.0]  // G4
]);

echo "File audio starter berhasil dibuat di " . realpath($audio_dir) . "\n";
