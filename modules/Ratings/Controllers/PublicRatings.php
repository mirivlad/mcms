<?php

declare(strict_types=1);

namespace MCMS\Modules\Ratings\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use MCMS\Modules\Ratings\Services\Ratings;

final class PublicRatings extends BaseController
{
    public function vote(): RedirectResponse|ResponseInterface
    {
        if (! service('throttler')->check('rating-vote-' . hash('sha256', $this->request->getIPAddress()), 10, 60)) {
            return $this->response->setStatusCode(429)->setBody(lang('Ratings.Errors.limit'));
        }
        $type = (string) $this->request->getPost('target_type');
        $id = (string) $this->request->getPost('target_id');
        $repository = service('mcmsModuleServices')->get('ratings.repository');
        assert($repository instanceof Ratings);
        $target = $repository->publicTarget($type, $id);
        if ($target === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $returnTo = site_url($target['url']) . '#ratings';
        session();
        $userId = auth()->loggedIn() ? (int) auth()->id() : null;
        if ($userId === null) {
            if (! (bool) setting('ratings.allow_guests')) {
                return redirect()->to($returnTo)->with('ratings_error', lang('Ratings.Public.login'));
            }
        }
        $captcha = service('mcmsModuleServices')->get('captcha.verifier');
        if ((method_exists($captcha, 'required') ? $captcha->required('ratings.vote', $userId !== null) : $userId === null) && ! $captcha->verify(
                'ratings.vote', session_id(),
                (string) $this->request->getPost('captcha_token'),
                (string) $this->request->getPost('captcha_answer'),
        )) {
            return redirect()->to($returnTo)->with('ratings_error', lang('Captcha.Public.invalid'));
        }
        $rating = (string) $this->request->getPost('stars');
        try {
            $repository->vote($type, $id, $userId, session_id(),
                in_array($rating, ['1', '2', '3', '4', '5'], true) ? (int) $rating : 0);
        } catch (InvalidArgumentException $e) {
            return redirect()->to($returnTo)->with('ratings_error', $e->getMessage());
        }
        return redirect()->to($returnTo)->with('ratings_notice', lang('Ratings.Public.saved'));
    }
}
