<?php namespace Quivi\Kobo\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Quivi\Kobo\Classes\ExternalFileStorage;
use Quivi\Kobo\Classes\SubmissionPreview;
use Quivi\Kobo\Models\Submission;
use Redirect;
use Symfony\Component\HttpFoundation\Response;

class Submissions extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController'    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Quivi.Kobo', 'main-menu-item', 'side-menu-item');
    }

    public function media($recordId = null, $attachmentIndex = null): Response
    {
        $submission = Submission::find($recordId);

        if (!$submission) {
            return new Response(trans('quivi.kobo::lang.review.errors.submission_not_found'), 404, ['Content-Type' => 'text/plain']);
        }

        try {
            $media = SubmissionPreview::make()->downloadAttachment($submission, (int) $attachmentIndex);
        } catch (\Throwable $ex) {
            return new Response($ex->getMessage(), 500, ['Content-Type' => 'text/plain']);
        }

        return new Response($media['body'], 200, $media['headers']);
    }

    public function external($recordId = null, $fileId = null)
    {
        $submission = Submission::find($recordId);

        if (!$submission) {
            return new Response(trans('quivi.kobo::lang.review.errors.submission_not_found'), 404, ['Content-Type' => 'text/plain']);
        }

        try {
            $file = SubmissionPreview::make()->findExternalFile($submission, (string) $fileId);
            if (!$file) {
                return new Response(trans('quivi.kobo::lang.review.errors.external_file_not_found'), 404, ['Content-Type' => 'text/plain']);
            }

            return Redirect::away((new ExternalFileStorage())->temporaryDownloadUrl($file));
        } catch (\Throwable $ex) {
            return new Response($ex->getMessage(), 500, ['Content-Type' => 'text/plain']);
        }
    }
}
