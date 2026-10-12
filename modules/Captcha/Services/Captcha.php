<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Captcha\Api\CaptchaInterface;
use RuntimeException;

final class Captcha implements CaptchaInterface
{
    private const TTL = 300;
    private const COLORS = [
        'red' => [191, 40, 43],
        'blue' => [34, 94, 190],
        'green' => [13, 126, 76],
    ];
    private const SHAPES = ['circle', 'square', 'triangle'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function issue(string $purpose, string $sessionId): array
    {
        $this->checkScope($purpose, $sessionId);
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException(lang('Captcha.Public.unavailable'));
        }
        $owner = hash('sha256', $sessionId);
        $now = time();
        // A DB-backed bound on issuance works consistently across application workers.
        $recent = $this->db->table('captcha_challenges')
            ->where('owner_hash', $owner)
            ->where('created_at >=', date('Y-m-d H:i:s', $now - 60))
            ->countAllResults();
        if ($recent >= 24) {
            throw new RuntimeException(lang('Captcha.Public.unavailable'));
        }
        if (random_int(1, 20) === 1) {
            $this->db->table('captcha_challenges')->where('expires_at <', date('Y-m-d H:i:s', $now))->delete();
        }

        $shape = self::SHAPES[random_int(0, 2)];
        $colorNames = array_keys(self::COLORS);
        $color = $colorNames[random_int(0, 2)];
        $count = random_int(2, 5);
        $items = array_fill(0, $count, ['shape' => $shape, 'color' => $color]);
        for ($i = $count; $i < 12; $i++) {
            do {
                $otherShape = self::SHAPES[random_int(0, 2)];
                $otherColor = $colorNames[random_int(0, 2)];
            } while ($otherShape === $shape && $otherColor === $color);
            $items[] = ['shape' => $otherShape, 'color' => $otherColor];
        }
        // Cryptographic shuffle, so position carries no information about the answer.
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        $pieces = [];
        foreach ($items as $i => $item) {
            $pieces[] = $item + [
                'x' => 45 + ($i % 4) * 83 + random_int(-9, 9),
                'y' => 29 + intdiv($i, 4) * 49 + random_int(-6, 6),
                'size' => random_int(13, 17),
                'angle' => random_int(-30, 30),
            ];
        }

        $token = bin2hex(random_bytes(24));
        $salt = bin2hex(random_bytes(16));
        $this->db->table('captcha_challenges')->insert([
            'token_hash' => hash('sha256', $token),
            'owner_hash' => $owner,
            'purpose' => $purpose,
            'answer_hash' => hash('sha256', $salt . ':' . $count),
            'salt' => $salt,
            'puzzle' => json_encode($pieces, JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $now + self::TTL),
            'used_at' => null,
        ]);

        return [
            'token' => $token,
            'question' => lang('Captcha.Public.question', [
                lang('Captcha.Shapes.' . $shape),
                lang('Captcha.Colors.' . $color),
            ]),
            'image_url' => site_url('captcha/image/' . $token),
            'expires_in' => self::TTL,
        ];
    }

    public function verify(string $purpose, string $sessionId, string $token, string $answer): bool
    {
        $this->checkScope($purpose, $sessionId);
        if (! self::validToken($token)) {
            return false;
        }
        $tokenHash = hash('sha256', $token);
        $ownerHash = hash('sha256', $sessionId);
        $now = date('Y-m-d H:i:s');
        $row = $this->db->table('captcha_challenges')
            ->where('token_hash', $tokenHash)
            ->where('owner_hash', $ownerHash)
            ->where('purpose', $purpose)
            ->where('used_at', null)
            ->where('expires_at >=', $now)
            ->get()->getRowArray();
        if ($row === null) {
            return false;
        }

        // Claim the challenge before checking the solution. Exactly one parallel
        // request can succeed; even wrong guesses burn the one-time token.
        $this->db->table('captcha_challenges')
            ->where('id', $row['id'])
            ->where('used_at', null)
            ->where('expires_at >=', $now)
            ->update(['used_at' => $now]);
        if ($this->db->affectedRows() !== 1) {
            return false;
        }

        $answer = trim($answer);
        if ($answer === '' || strlen($answer) > 2 || ! ctype_digit($answer)) {
            return false;
        }
        $expectedHash = hash('sha256', $row['salt'] . ':' . (int) $answer);
        return hash_equals($row['answer_hash'], $expectedHash);
    }

    public function image(string $token): ?string
    {
        if (! self::validToken($token) || ! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $row = $this->db->table('captcha_challenges')
            ->select('puzzle')
            ->where('token_hash', hash('sha256', $token))
            ->where('used_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->get()->getRowArray();
        if ($row === null) {
            return null;
        }
        $items = json_decode($row['puzzle'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($items) || count($items) !== 12) {
            return null;
        }
        $image = imagecreatetruecolor(340, 158);
        if ($image === false) {
            return null;
        }
        $white = imagecolorallocate($image, 249, 249, 247);
        $gray = imagecolorallocate($image, 220, 224, 227);
        imagefilledrectangle($image, 0, 0, 339, 157, $white);
        // Background texture is generated per rendering; not part of the answer.
        for ($i = 0; $i < 26; $i++) {
            imageline($image, random_int(0, 339), random_int(0, 157),
                random_int(0, 339), random_int(0, 157), $gray);
        }
        foreach ($items as $piece) {
            $coords = self::COLORS[$piece['color']] ?? null;
            if ($coords === null || ! in_array($piece['shape'], self::SHAPES, true)) {
                imagedestroy($image);
                return null;
            }
            $paint = imagecolorallocate($image, ...$coords);
            $x = (int) $piece['x'];
            $y = (int) $piece['y'];
            $r = (int) $piece['size'];
            $angle = deg2rad((int) $piece['angle']);
            if ($piece['shape'] === 'circle') {
                imagefilledellipse($image, $x, $y, $r * 2, $r * 2, $paint);
                continue;
            }
            $sides = $piece['shape'] === 'triangle' ? 3 : 4;
            $offset = $piece['shape'] === 'triangle' ? -M_PI / 2 : M_PI / 4;
            $points = [];
            for ($v = 0; $v < $sides; $v++) {
                $a = $offset + $angle + 2 * M_PI * $v / $sides;
                $points[] = (int) round($x + $r * cos($a));
                $points[] = (int) round($y + $r * sin($a));
            }
            imagefilledpolygon($image, $points, $paint);
        }
        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();
        imagedestroy($image);
        return is_string($png) ? $png : null;
    }

    private function checkScope(string $purpose, string $sessionId): void
    {
        if (preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $purpose) !== 1 || $sessionId === '') {
            throw new InvalidArgumentException('Invalid captcha purpose or session');
        }
    }

    private static function validToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{48}$/D', $token) === 1;
    }
}
