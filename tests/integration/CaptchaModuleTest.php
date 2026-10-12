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
        for ($n = 2; $n <= 5; $n++) {
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
