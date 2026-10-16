<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Api;

interface CaptchaInterface
{
    /**
     * Issue a single-use challenge tied to a specific form purpose and session.
     * @return array{token:string,kind:string,question:string,text_question:string,visual_question:string,image_url:string,image_width:int,image_height:int,expires_in:int}
     */
    public function issue(string $purpose, string $sessionId): array;

    /** Returns false for expired, incorrect, cross-session or replayed challenges. */
    public function verify(string $purpose, string $sessionId, string $token, string $answer): bool;

    /** Render a safe PNG for a valid unexpired challenge, or null if not found. */
    public function image(string $token): ?string;
}
