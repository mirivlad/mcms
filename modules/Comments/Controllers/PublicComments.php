<?php

declare(strict_types=1);

namespace MCMS\Modules\Comments\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use MCMS\Modules\Comments\Services\Comments;

final class PublicComments extends BaseController
{
    public function create(): RedirectResponse|ResponseInterface
    {
        if (! service('throttler')->check('comment-create-' . hash('sha256', $this->request->getIPAddress()), 5, 60)) {
            return $this->response->setStatusCode(429)->setBody(lang('Comments.Errors.limit'));
        }
        $type = (string) $this->request->getPost('target_type');
        $id = (string) $this->request->getPost('target_id');
        $repository = $this->repository();
        $target = $repository->publicTarget($type, $id);
        if ($target === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $url = site_url($target['url']) . '#comments';
        if (trim((string) $this->request->getPost('website')) !== '') {
            return redirect()->to($url);
        }
        session();
        $userId = auth()->loggedIn() ? (int) auth()->id() : null;
        if ($userId === null) {
            if (! (bool) setting('comments.allow_guests')) {
                return redirect()->to($url)->with('comments_error', lang('Comments.Public.login'));
            }
        }
        $captcha = service('mcmsModuleServices')->get('captcha.verifier');
        if ((method_exists($captcha, 'required') ? $captcha->required('comments.create', $userId !== null) : $userId === null) && ! $captcha->verify(
                'comments.create', session_id(),
                (string) $this->request->getPost('captcha_token'),
                (string) $this->request->getPost('captcha_answer'),
        )) {
            return redirect()->to($url)->withInput()->with('comments_error', lang('Captcha.Public.invalid'));
        }
        $parent = $this->request->getPost('parent_id');
        $parentId = is_scalar($parent) && ctype_digit((string) $parent) && (int) $parent > 0
            ? (int) $parent : null;
        try {
            $comment = $repository->create(
                $type, $id, $parentId, $userId,
                $userId !== null ? (string) auth()->user()->username : (string) $this->request->getPost('name'),
                $userId !== null ? '' : (string) $this->request->getPost('email'),
                (string) $this->request->getPost('body'),
            );
            return redirect()->to($url)->with('comments_notice', lang($comment['status'] === 'published' ? 'Comments.Public.sent' : 'Comments.Public.pending'));
        } catch (InvalidArgumentException $e) {
            return redirect()->to($url)->withInput()->with('comments_error', $e->getMessage());
        }
    }

    public function report(): RedirectResponse|ResponseInterface
    {
        if (! service('throttler')->check('comment-report-' . hash('sha256', $this->request->getIPAddress()), 3, 60)) {
            return $this->response->setStatusCode(429);
        }
        session();
        $commentId = (int) $this->request->getPost('comment_id');
        $type = (string) $this->request->getPost('target_type');
        $id = (string) $this->request->getPost('target_id');
        $repository = $this->repository();
        $target = $repository->publicTarget($type, $id);
        if ($target === null || $commentId < 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        // Do not allow a report on a comment from another resource.
        $comment = db_connect()->table('comments')->where('id', $commentId)
            ->where('target_type', $type)->where('target_id', $id)
            ->where('status', 'published')->get()->getRowArray();
        if ($comment === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        try {
            $actor = auth()->loggedIn() ? 'user:' . auth()->id() : 'guest:' . session_id();
            $repository->report($commentId, $actor, (string) $this->request->getPost('reason'));
        } catch (InvalidArgumentException $e) {
            return redirect()->to(site_url($target['url']) . '#comments')->with('comments_error', $e->getMessage());
        }
        return redirect()->to(site_url($target['url']) . '#comments')->with('comments_notice', lang('Comments.Public.reported'));
    }

    private function repository(): Comments
    {
        return service('mcmsModuleServices')->get('comments.repository');
    }
}
