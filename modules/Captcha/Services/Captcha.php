<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Captcha\Api\CaptchaInterface;
use RuntimeException;

final class Captcha implements CaptchaInterface
{
    public const PURPOSES = ['auth.register', 'feedback.contact', 'comments.create', 'ratings.vote'];
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
        $owner = hash('sha256', $sessionId);
        $now = time();
        $options = $this->options();
        $this->expire();
        if (! $this->consume('issue-ip:' . $this->ip(), $options['ip_limit'])
            || ! $this->consume('issue-owner:' . $owner, $options['session_limit'])) {
            $this->metric($purpose, 'limited');
            throw new RuntimeException(lang('Captcha.Public.unavailable'));
        }

        $difficulty = in_array($options['difficulty'], ['easy', 'standard', 'hard'], true)
            ? $options['difficulty'] : 'easy';
        $colors = array_keys(self::COLORS);
        $firstShape = self::SHAPES[random_int(0, 2)];
        $firstColor = $colors[random_int(0, 2)];
        $secondShape = null;
        $secondColor = null;

        if ($difficulty === 'hard') {
            // Use two distinct shape AND color combinations; both must be counted.
            do {
                $secondShape = self::SHAPES[random_int(0, 2)];
                $secondColor = $colors[random_int(0, 2)];
            } while ($secondShape === $firstShape || $secondColor === $firstColor);
            $firstCount = random_int(2, 4);
            $secondCount = random_int(2, 4);
            $total = 20;
            $question = lang('Captcha.Public.questionHard', [
                lang('Captcha.ColorsPlural.' . $firstColor), lang('Captcha.ShapesPlural.' . $firstShape),
                lang('Captcha.ColorsPlural.' . $secondColor), lang('Captcha.ShapesPlural.' . $secondShape),
            ]);
        } else {
            $firstCount = random_int(2, $difficulty === 'easy' ? 9 : 6);
            $secondCount = 0;
            $total = 12;
            $question = lang('Captcha.Public.question', [
                lang('Captcha.ShapesPlural.' . $firstShape),
                lang('Captcha.ColorsPlural.' . $firstColor),
            ]);
        }
        $answer = $firstCount + $secondCount;

        $items = array_fill(0, $firstCount, ['shape' => $firstShape, 'color' => $firstColor]);
        if ($secondShape !== null && $secondColor !== null) {
            array_push($items, ...array_fill(0, $secondCount, ['shape' => $secondShape, 'color' => $secondColor]));
        }
        while (count($items) < $total) {
            $shape = self::SHAPES[random_int(0, 2)];
            $color = $colors[random_int(0, 2)];
            if (($shape === $firstShape && $color === $firstColor)
                || ($shape === $secondShape && $color === $secondColor)) {
                continue;
            }
            $items[] = ['shape' => $shape, 'color' => $color];
        }

        // A cryptographic shuffle prevents fixed positions from identifying targets.
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        $columns = $difficulty === 'hard' ? 5 : 4;
        $pieces = [];
        foreach ($items as $i => $item) {
            $pieces[] = $item + [
                'x' => 44 + ($i % $columns) * 83 + random_int(-7, 7),
                'y' => 29 + intdiv($i, $columns) * ($difficulty === 'hard' ? 53 : 49) + random_int(-5, 5),
                'size' => random_int(12, 16),
                'angle' => random_int(-30, 30),
            ];
        }

        if ($difficulty === 'easy') {
            // Addition or subtraction, with no negative operands or totals over 10.
            if (random_int(0, 1) === 0) {
                $left = random_int(1, $answer - 1);
                $textQuestion = lang('Captcha.Public.addition', [$left, $answer - $left]);
            } else {
                $offset = random_int(1, 10 - $answer);
                $textQuestion = lang('Captcha.Public.subtraction', [$answer + $offset, $offset]);
            }
        } elseif ($difficulty === 'standard') {
            // Accessible alternative: two operations instead of a trivial one-step equation.
            $remove = random_int(2, 4);
            $add = random_int(2, 4);
            $textQuestion = lang('Captcha.Public.storyStandard', [
                $answer + $remove, $add, $remove + $add,
            ]);
        } else {
            // Accessible alternative: three operations leading to the same answer.
            $remove = random_int(2, 4);
            $add1 = random_int(2, 4);
            $add2 = random_int(2, 4);
            $textQuestion = lang('Captcha.Public.storyHard', [
                $answer + $remove, $add1, $add2, $remove + $add1 + $add2,
            ]);
        }

        $token = bin2hex(random_bytes(24));
        $salt = bin2hex(random_bytes(16));
        $this->db->table('captcha_challenges')->insert([
            'token_hash' => hash('sha256', $token),
            'owner_hash' => $owner,
            'purpose' => $purpose,
            'answer_hash' => hash('sha256', $salt . ':' . $answer),
            'salt' => $salt,
            'puzzle' => json_encode($pieces, JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $now + $options['ttl']),
            'used_at' => null,
        ]);

        $this->metric($purpose, 'issued');
        return [
            'token' => $token,
            'kind' => $difficulty === 'easy' ? 'math' : 'visual',
            'question' => $question,
            'text_question' => $textQuestion,
            'visual_question' => $question,
            'image_url' => site_url('captcha/image/' . $token),
            'image_width' => $difficulty === 'hard' ? 430 : 340,
            'image_height' => $difficulty === 'hard' ? 230 : 158,
            'expires_in' => $options['ttl'],
        ];
    }

    public function verify(string $purpose, string $sessionId, string $token, string $answer): bool
    {
        $this->checkScope($purpose, $sessionId);
        if (! $this->consume('verify-ip:' . $this->ip(), $this->options()['verify_limit'])
            || ! $this->consume('verify-owner:' . hash('sha256', $sessionId), $this->options()['verify_limit'])) {
            $this->metric($purpose, 'limited');
            return false;
        }
        $this->expire();
        if (! self::validToken($token)) {
            $this->metric($purpose, 'failed');
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $row = $this->db->table('captcha_challenges')
            ->where('token_hash', hash('sha256', $token))
            ->where('owner_hash', hash('sha256', $sessionId))
            ->where('purpose', $purpose)->where('used_at', null)
            ->where('expires_at >=', $now)->get()->getRowArray();
        if ($row === null) {
            $this->metric($purpose, 'failed');
            return false;
        }
        $answer = trim($answer);
        $valid = $answer !== '' && strlen($answer) <= 2 && ctype_digit($answer)
            && hash_equals($row['answer_hash'], hash('sha256', $row['salt'] . ':' . (int) $answer));
        $outcome = $valid ? 'passed' : 'failed';
        // The conditional claim is atomic across workers; every guess burns its token.
        $this->db->table('captcha_challenges')->where('id', $row['id'])->where('used_at', null)
            ->where('expires_at >=', $now)->update(['used_at' => $now, 'outcome' => $outcome]);
        if ($this->db->affectedRows() !== 1) {
            $this->metric($purpose, 'failed');
            return false;
        }
        $this->metric($purpose, $outcome);
        return $valid;
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
        if (! is_array($items) || ! in_array(count($items), [12, 20], true)) {
            return null;
        }
        // Support existing 12-piece tokens and new 20-piece hard puzzles.
        $width = count($items) === 20 ? 430 : 340;
        $height = count($items) === 20 ? 230 : 158;
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return null;
        }
        $white = imagecolorallocate($image, 249, 249, 247);
        $gray = imagecolorallocate($image, 220, 224, 227);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);
        // Background texture is generated per rendering; not part of the answer.
        for ($i = 0; $i < (count($items) === 20 ? 36 : 26); $i++) {
            imageline($image, random_int(0, $width - 1), random_int(0, $height - 1),
                random_int(0, $width - 1), random_int(0, $height - 1), $gray);
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

    public function options(): array
    {
        $options = array_replace([
            'difficulty' => 'easy', 'ttl' => 300, 'session_limit' => 24,
            'ip_limit' => 60, 'verify_limit' => 120,
            'policies' => array_fill_keys(self::PURPOSES, 'guests'),
        ], (array) setting('captcha.options'));
        $options['policies'] = array_replace(array_fill_keys(self::PURPOSES, 'guests'), (array) $options['policies']);
        return $options;
    }

    public function saveOptions(array $input): void
    {
        $values = [];
        foreach (['ttl' => [120, 900], 'session_limit' => [4, 120], 'ip_limit' => [8, 600], 'verify_limit' => [8, 600]] as $key => [$min, $max]) {
            $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                throw new InvalidArgumentException(lang('Captcha.Settings.invalid'));
            }
            $values[$key] = $value;
        }
        $difficulty = $input['difficulty'] ?? null;
        if (! in_array($difficulty, ['easy', 'standard', 'hard'], true)) {
            throw new InvalidArgumentException(lang('Captcha.Settings.invalid'));
        }
        $values['difficulty'] = $difficulty;
        $policies = $input['policies'] ?? [];
        if (! is_array($policies)) {
            throw new InvalidArgumentException(lang('Captcha.Settings.invalid'));
        }
        $values['policies'] = [];
        foreach (self::PURPOSES as $purpose) {
            $policy = $policies[$purpose] ?? 'guests';
            if (! in_array($policy, ['off', 'guests', 'all'], true)) {
                throw new InvalidArgumentException(lang('Captcha.Settings.invalid'));
            }
            $values['policies'][$purpose] = $policy;
        }
        service('settings')->set('captcha.options', $values);
    }

    public function required(string $purpose, bool $authenticated): bool
    {
        $policy = $this->options()['policies'][$purpose] ?? 'guests';
        return $policy === 'all' || ($policy === 'guests' && ! $authenticated);
    }

    public function statistics(): array
    {
        $this->expire();
        return $this->db->table('captcha_metrics')->select('purpose, outcome, SUM(total) AS total', false)
            ->where('day >=', date('Y-m-d', time() - 6 * 86400))->groupBy(['purpose', 'outcome'])->get()->getResultArray();
    }

    public function recordImageLimit(): void
    {
        $this->metric('captcha.image', 'limited');
    }

    private function expire(): void
    {
        $now = date('Y-m-d H:i:s');
        $purposes = $this->db->table('captcha_challenges')->select('purpose')->where('used_at', null)
            ->where('expires_at <', $now)->groupBy('purpose')->get()->getResultArray();
        foreach ($purposes as $entry) {
            $this->db->table('captcha_challenges')->where('purpose', $entry['purpose'])->where('used_at', null)
                ->where('expires_at <', $now)->update(['used_at' => $now, 'outcome' => 'expired']);
            $count = $this->db->affectedRows();
            if ($count > 0) {
                $this->metric($entry['purpose'], 'expired', $count);
            }
        }
        if (random_int(1, 20) === 1) {
            $this->db->table('captcha_challenges')->where('used_at <', date('Y-m-d H:i:s', time() - 86400))->delete();
            $this->db->table('captcha_limits')->where('window_start <', time() - 3600)->delete();
            $this->db->table('captcha_metrics')->where('day <', date('Y-m-d', time() - 90 * 86400))->delete();
        }
    }

    private function consume(string $scope, int $limit): bool
    {
        $key = ['scope_hash' => hash('sha256', $scope), 'window_start' => intdiv(time(), 60) * 60];
        $this->db->table('captcha_limits')->ignore(true)->insert([...$key, 'hits' => 0]);
        $this->db->table('captcha_limits')->where($key)->where('hits <', $limit)->set('hits', 'hits + 1', false)->update();
        return $this->db->affectedRows() === 1;
    }

    private function metric(string $purpose, string $outcome, int $count = 1): void
    {
        $key = ['day' => date('Y-m-d'), 'purpose' => $purpose, 'outcome' => $outcome];
        $this->db->table('captcha_metrics')->ignore(true)->insert([...$key, 'total' => 0]);
        $this->db->table('captcha_metrics')->where($key)->set('total', 'total + ' . $count, false)->update();
    }

    private function ip(): string
    {
        $request = service('request');
        return method_exists($request, 'getIPAddress') ? hash('sha256', $request->getIPAddress()) : 'cli';
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
