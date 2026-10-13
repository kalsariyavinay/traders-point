<?php

namespace App\Http\Requests\Admin;

use App\Models\Video;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends StoreVideoRequest
{
    /**
     * Leaving the file input empty keeps the video already on disk, so a new
     * upload is only mandatory when there is no stored file to fall back on.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Video $video */
        $video = $this->route('video');

        return [
            ...$this->detailRules(),
            'video_file' => [
                Rule::requiredIf(
                    fn () => $this->input('video_source') === 'upload' && $video->isExternalVideo()
                ),
                'nullable',
                'file',
                'mimetypes:'.self::VIDEO_MIMETYPES,
                'max:'.self::MAX_VIDEO_KB,
            ],
        ];
    }
}
