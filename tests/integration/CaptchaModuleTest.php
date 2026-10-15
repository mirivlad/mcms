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

    private function answer(string $token): string
    {
        $row = $this->db->table('captcha_challenges')
            ->where('token_hash', hash('sha256', $token))->get()->getRowArray();
        self::assertNotNull($row);
        $pieces = json_decode($row['puzzle'], true, 512, JSON_THROW_ON_ERROR);
        $counts = [];
        foreach ($pieces as $piece) {
            $key = $piece['shape'] . '-' . $piece['color'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        for ($n = 2; $n <= 9; $n++) {
            if (hash_equals($row['answer_hash'], hash('sha256', $row['salt'] . ':' . $n))) {
                self::assertContains($n, array_values($counts));
                return (string) $n;
            }
        }
        self::fail('Generated puzzle has an invalid answer');
    }

    public function testSingleUseChallengeValidatesPurposeSessionAndImage(): void
    {
        service('language')->setLocale('en');
        $captcha = $this->verifier();
        $challenge = $captcha->issue('feedback.contact', 'session-1');
        self::assertSame(300, $challenge['expires_in']);
        self::assertStringContainsString('captcha/image/', $challenge['image_url']);
        self::assertStringContainsString('How many', $challenge['question']);
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

    public function testAccessibleTextAnswerAndAllFormPolicies(): void
    {
        service('language')->setLocale('en');
        $captcha = $this->verifier();
        foreach (['easy', 'standard', 'hard'] as $level) {
            $captcha->saveOptions([...$captcha->options(), 'difficulty' => $level, 'ttl' => 120]);
            $challenge = $captcha->issue('comments.create', 'text-session');
            self::assertSame(120, $challenge['expires_in']);
            self::assertSame(1, preg_match('/What is ([0-9]+) (plus|minus) ([0-9]+)\?/', $challenge['text_question'], $match));
            $answer = $match[2] === 'plus' ? (int) $match[1] + (int) $match[3] : (int) $match[1] - (int) $match[3];
            self::assertSame((string) $answer, $this->answer($challenge['token']));
            self::assertTrue($captcha->verify('comments.create', 'text-session', $challenge['token'], (string) $answer));
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
