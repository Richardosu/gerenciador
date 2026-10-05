<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadTaskAttachmentController extends Controller
{
    public function __invoke(Task $task, Media $media): StreamedResponse
    {
        Gate::authorize('view', $task);

        abort_unless(
            (int) $media->model_id === (int) $task->getKey()
            && $media->model_type === $task->getMorphClass()
            && $media->collection_name === 'attachments',
            404,
        );

        return $media->toResponse(request());
    }
}
