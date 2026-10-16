<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Modules\Captcha\Api\CaptchaInterface;

final class CaptchaModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings'];

    protected function tearDown(): void
    {
        service('settings')->forget('captcha.options');
        $this->migrations->setNamespace('MCMS\Modules\Captcha')->regress(0, 'tests');
        parent::tearDown();
    }

    private function verifier(): CaptchaInterface
    {
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules');
        $manager->enable('captcha');
        $manager->bootEnabled();
        self::assertTrue($services->has('captcha.verifier'));
        $verifier = $services->get('captcha.verifier');
        self::assertInstanceOf(CaptchaInterface::class, $verifier);
        return $verifier;
    }

    private function puzzle(string $token): array
    {
        $row = $this->db->table('captcha_challenges')
            ->where('token_hash', hash('sha256', $token))->get()->getRowArray();
        self::assertNotNull($row);
        return [
            'row' => $row,
            'pieces' => json_decode($row['puzzle'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    private function answer(string $token): string
    {
        $row = $this->puzzle($token)['row'];
        for ($n = 2; $n <= 9; $n++) {
            if (hash_equals($row['answer_hash'], hash('sha256', $row['salt'] . ':' . $n))) {
                return (string) $n;
            }
        }
        self::fail('Generated puzzle has an invalid answer');
    }

    private function visualAnswer(array $challenge): int
    {
        $pieces = $this->puzzle($challenge['token'])['pieces'];
        $colors = ['red', 'blue', 'green'];
        $shapes = ['circle' => 'circles', 'square' => 'squares', 'triangle' => 'triangles'];
        $targets = [];
        foreach ($colors as $color) {
            foreach ($shapes as $shape => $plural) {
                if (str_contains($challenge['visual_question'], $color . ' ' . $plural)) {
                    $targets[] = ['color' => $color, 'shape' => $shape];
                }
            }
        }
        self::assertCount($challenge['image_width'] === 430 ? 2 : 1, $targets);
        return count(array_filter($pieces, static function (array $piece) use ($targets): bool {
            foreach ($targets as $target) {
                if ($piece['color'] === $target['color'] && $piece['shape'] === $target['shape']) {
                    return true;
                }
            }
            return false;
        }));
    }

    public function testSingleUseChallengeValidatesPurposeSessionAndImage(): void
    {
        service('language')->setLocale('en');
        $captcha = $this->verifier();
        $challenge = $captcha->issue('feedback.contact', 'session-1');
        self::assertSame(300, $challenge['expires_in']);
        self::assertStringContainsString('captcha/image/', $challenge['image_url']);
        self::assertSame('math', $challenge['kind']);
        self::assertStringContainsString('What is', $challenge['text_question']);
        self::assertStringContainsString('How many', $challenge['visual_question']);
        self::assertSame(340, $challenge['image_width']);
        self::assertSame(158, $challenge['image_height']);
        $png = $captcha->image($challenge['token']);
        self::assertNotNull($png);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $correct = $this->answer($challenge['token']);
        self::assertFalse($captcha->verify('other.form', 'session-1', $challenge['token'], $correct));
        self::assertFalse($captcha->verify('feedback.contact', 'session-2', $challenge['token'], $correct));
        self::assertTrue($captcha->verify('feedback.contact', 'session-1', $challenge['token'], $correct));
        self::assertFalse($captcha->verify('feedback.contact', 'session-1', $challenge['token'], $correct));
        self::assertNull($captcha->image($challenge['token']));
    }

    public function testWrongAnswerConsumesChallengeAndExpiredFails(): void
    {
        $captcha = $this->verifier();
        $challenge = $captcha->issue('comments.create', 'session-2');
        $correct = $this->answer($challenge['token']);
        $wrong = $correct === '1' ? '9' : '1';
        self::assertFalse($captcha->verify('comments.create', 'session-2', $challenge['token'], $wrong));
        self::assertFalse($captcha->verify('comments.create', 'session-2', $challenge['token'], $correct));
        $expired = $captcha->issue('comments.create', 'session-2');
        $this->db->table('captcha_challenges')
            ->where('token_hash', hash('sha256', $expired['token']))
            ->update(['expires_at' => '2020-01-01 00:00:00']);
        self::assertFalse($captcha->verify('comments.create', 'session-2', $expired['token'], $this->answer($expired['token'])));
        self::assertNull($captcha->image($expired['token']));
        self::assertFalse($captcha->verify('comments.create', 'session-2', 'bad', '2'));
    }

    public function testDifficultyControlsPrimaryPuzzleAndEqualTextAndImageAnswers(): void
    {
        service('language')->setLocale('en');
        $captcha = $this->verifier();

        foreach (['easy', 'standard', 'hard'] as $level) {
            $captcha->saveOptions([...$captcha->options(), 'difficulty' => $level, 'ttl' => 120]);
            for ($sample = 0; $sample < 6; $sample++) {
                $challenge = $captcha->issue('comments.create', 'difficulty-' . $level);
                $answer = (int) $this->answer($challenge['token']);
                $pieces = $this->puzzle($challenge['token'])['pieces'];

                self::assertSame(120, $challenge['expires_in']);
                self::assertSame($level === 'easy' ? 'math' : 'visual', $challenge['kind']);
                self::assertCount($level === 'hard' ? 20 : 12, $pieces);
                self::assertSame($level === 'hard' ? 430 : 340, $challenge['image_width']);
                self::assertSame($level === 'hard' ? 230 : 158, $challenge['image_height']);
                self::assertSame($answer, $this->visualAnswer($challenge));

                if ($level === 'easy') {
                    self::assertSame(1, preg_match('/What is ([0-9]+) (plus|minus) ([0-9]+)\?/', $challenge['text_question'], $m));
                    $fromText = $m[2] === 'plus' ? (int) $m[1] + (int) $m[3] : (int) $m[1] - (int) $m[3];
                    self::assertLessThanOrEqual(10, (int) $m[1]);
                    self::assertSame($challenge['visual_question'], $challenge['question']);
                } elseif ($level === 'standard') {
                    self::assertSame(1, preg_match('/There were (\d+) tokens\. (\d+) were added, then (\d+) were removed\./', $challenge['text_question'], $m));
                    $fromText = (int) $m[1] + (int) $m[2] - (int) $m[3];
                    self::assertStringContainsString('How many', $challenge['question']);
                } else {
                    self::assertSame(1, preg_match('/There were (\d+) tokens\. (\d+) were added, then another (\d+) were added, and (\d+) were removed\./', $challenge['text_question'], $m));
                    $fromText = (int) $m[1] + (int) $m[2] + (int) $m[3] - (int) $m[4];
                    self::assertStringContainsString('in total', $challenge['question']);
                }
                self::assertSame($answer, $fromText);
                $png = $captcha->image($challenge['token']);
                self::assertNotNull($png);
                self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
                self::assertSame([$challenge['image_width'], $challenge['image_height']], array_slice(getimagesizefromstring($png), 0, 2));

                $html = view('MCMS\\Modules\\Captcha\\Views\\widget', compact('challenge'));
                $firstPart = explode('<details', $html, 2)[0];
                if ($level === 'easy') {
                    self::assertStringNotContainsString('<img', $firstPart);
                } else {
                    self::assertStringContainsString('<img', $firstPart);
                    self::assertStringContainsString('Use a text question', $html);
                }
                self::assertTrue($captcha->verify('comments.create', 'difficulty-' . $level, $challenge['token'], (string) $answer));
            }
        }

        foreach (\MCMS\Modules\Captcha\Services\Captcha::PURPOSES as $purpose) {
            self::assertTrue($captcha->required($purpose, false));
            self::assertFalse($captcha->required($purpose, true));
        }
        $captcha->saveOptions([...$captcha->options(), 'policies' => ['comments.create' => 'all', 'feedback.contact' => 'off']]);
        self::assertTrue($captcha->required('comments.create', true));
        self::assertFalse($captcha->required('feedback.contact', false));
        self::assertTrue($captcha->required('auth.register', false));
    }

    public function testRussianVisualQuestionUsesPluralNamesAndAccessibleAlternative(): void
    {
        service('language')->setLocale('ru');
        $captcha = $this->verifier();
        $captcha->saveOptions([...$captcha->options(), 'difficulty' => 'hard']);
        $challenge = $captcha->issue('ratings.vote', 'russian-challenge');
        self::assertSame('visual', $challenge['kind']);
        self::assertStringStartsWith('Сколько всего ', $challenge['question']);
        self::assertStringContainsString(' на рисунке?', $challenge['question']);
        self::assertSame(2, substr_count($challenge['question'], ' и ') + 1);
        self::assertStringContainsString('Было ', $challenge['text_question']);
        self::assertStringContainsString('Сколько осталось?', $challenge['text_question']);
        $html = view('MCMS\Modules\Captcha\Views\widget', compact('challenge'));
        self::assertStringContainsString('Решить текстовое задание вместо рисунка', $html);
        self::assertStringContainsString('width="430"', $html);
        self::assertTrue($captcha->verify('ratings.vote', 'russian-challenge', $challenge['token'], $this->answer($challenge['token'])));
    }

    public function testExpirationMetricsAreCountedOnceAndWrongTokensAreMeasured(): void
    {
        $captcha = $this->verifier();
        $challenge = $captcha->issue('feedback.contact', 'metric-session');
        $this->db->table('captcha_challenges')->where('token_hash', hash('sha256', $challenge['token']))->update(['expires_at' => '2020-01-01 00:00:00']);
        $captcha->statistics();
        $captcha->statistics();
        $expired = $this->db->table('captcha_metrics')->where('outcome', 'expired')->get()->getRowArray();
        self::assertSame(1, (int) $expired['total']);
        self::assertFalse($captcha->verify('feedback.contact', 'metric-session', 'bad-token', '1'));
        $failed = $this->db->table('captcha_metrics')->where('outcome', 'failed')->get()->getRowArray();
        self::assertSame(1, (int) $failed['total']);
        self::assertSame('expired', $this->db->table('captcha_challenges')->where('token_hash', hash('sha256', $challenge['token']))->get()->getRowArray()['outcome']);
    }

    public function testSharedIpLimitSurvivesSessionRotationAndRejectsInvalidSettings(): void
    {
        $captcha = $this->verifier();
        $captcha->saveOptions([...$captcha->options(), 'ip_limit' => 8]);
        for ($i = 0; $i < 8; $i++) {
            $captcha->issue('feedback.contact', 'rotating-' . $i);
        }
        try {
            $captcha->issue('feedback.contact', 'fresh-session');
            self::fail('New session bypassed IP issuance limit');
        } catch (RuntimeException) {
        }
        self::assertSame(8, $this->db->table('captcha_challenges')->countAllResults());
        self::assertSame(1, (int) $this->db->table('captcha_metrics')->where('outcome', 'limited')->get()->getRowArray()['total']);
        try {
            $captcha->saveOptions([...$captcha->options(), 'ttl' => 99999]);
            self::fail('Unbounded TTL accepted');
        } catch (InvalidArgumentException) {
        }
        self::assertSame(300, $captcha->options()['ttl']);
    }

    public function testIssuanceBoundPreventsUnlimitedSessionChallenges(): void
    {
        $captcha = $this->verifier();
        for ($i = 0; $i < 24; $i++) {
            $captcha->issue('feedback.contact', 'rate-session');
        }
        $this->expectException(RuntimeException::class);
        $captcha->issue('feedback.contact', 'rate-session');
    }
}
