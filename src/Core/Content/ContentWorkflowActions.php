<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

use CodeIgniter\HTTP\ResponseInterface;

/** Session-only editor endpoints. The module supplies its repository and permission. */
trait ContentWorkflowActions
{
    public function draft(): ResponseInterface
    {
        $id = max(0, (int) ($this->request->getMethod() === 'POST'
            ? $this->request->getPost('content_id') : $this->request->getGet('content_id')));
        if (($denied = $this->workflowAccess($id)) !== null) {
            return $denied;
        }
        if ($this->request->getMethod() === 'POST') {
            try {
                $this->workflow()->saveDraft((int) auth()->id(), $id, $this->workflowDraftData());
                return $this->workflowResponse(['saved' => true, 'csrfHash' => csrf_hash()]);
            } catch (\Throwable $e) {
                log_message('error', 'Content autosave failed: {error}', ['error' => $e->getMessage()]);
                return $this->workflowResponse(['error' => lang('MCMS.content.saveFailed'), 'csrfHash' => csrf_hash()], 422);
            }
        }
        return $this->workflowResponse(['draft' => $this->workflow()->draft((int) auth()->id(), $id)]);
    }

    public function discardDraft(): ResponseInterface
    {
        $id = max(0, (int) $this->request->getPost('content_id'));
        if (($denied = $this->workflowAccess($id)) !== null) {
            return $denied;
        }
        $this->workflow()->discardDraft((int) auth()->id(), $id);
        return $this->workflowResponse(['discarded' => true, 'csrfHash' => csrf_hash()]);
    }

    public function history(int $id): ResponseInterface
    {
        if (($denied = $this->workflowAccess($id)) !== null) {
            return $denied;
        }
        return $this->workflowResponse($this->workflow()->history($id, max(0, (int) $this->request->getGet('offset'))));
    }

    public function revision(int $id, int $revisionId): ResponseInterface
    {
        if (($denied = $this->workflowAccess($id)) !== null) {
            return $denied;
        }
        $snapshot = $this->workflow()->revision($id, $revisionId);
        return $snapshot === null
            ? $this->workflowResponse(['error' => lang('MCMS.content.versionMissing')], 404)
            : $this->workflowResponse(['data' => $snapshot]);
    }

    private function workflowDraftData(): array
    {
        return $this->formData();
    }

    private function workflowAccess(int $id): ?ResponseInterface
    {
        if (! $this->workflowPermission($id)) {
            return $this->workflowResponse(['error' => lang('MCMS.errors.permissionDenied'), 'csrfHash' => csrf_hash()], 403);
        }
        if ($id > 0 && $this->workflowContent($id) === null) {
            return $this->workflowResponse(['error' => lang('MCMS.content.versionMissing'), 'csrfHash' => csrf_hash()], 404);
        }
        return null;
    }

    private function workflowResponse(array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->setStatusCode($status)->setJSON($data);
    }
}
