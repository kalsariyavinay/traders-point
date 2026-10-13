<?php

namespace App\Http\Requests\Admin;

use App\Enums\VideoStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    /** Accepted container formats for an uploaded video file. */
    public const VIDEO_MIMETYPES = 'video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska';

    /** Kilobytes. Kept below php.ini post_max_size (128M) to leave request headroom. */
    public const MAX_VIDEO_KB = 122880;

    public const MAX_THUMBNAIL_KB = 2048;

    /**
     * The admin middleware already guards these routes.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Checkboxes are absent when unchecked, and a free video is always priced at zero.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_free' => $this->boolean('is_free'),
            'video_source' => $this->input('video_source', 'upload'),
        ]);

        if ($this->boolean('is_free')) {
            $this->merge(['price' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->detailRules(),
            'video_file' => [
                Rule::requiredIf(fn () => $this->input('video_source') === 'upload'),
                'nullable',
                'file',
                'mimetypes:'.self::VIDEO_MIMETYPES,
                'max:'.self::MAX_VIDEO_KB,
            ],
        ];
    }

    /**
     * Rules shared by creating and updating a video.
     *
     * @return array<string, mixed>
     */
    protected function detailRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Drives the 90% completion rule and therefore certificates.
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'status' => ['required', Rule::enum(VideoStatus::class)],
            'is_free' => ['required', 'boolean'],
            'price' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_free')),
                'numeric',
                $this->boolean('is_free') ? 'min:0' : 'min:1',
                'max:99999999.99',
            ],
            'video_source' => ['required', 'in:upload,link'],
            'video_url' => [
                Rule::requiredIf(fn () => $this->input('video_source') === 'link'),
                'nullable',
                'url',
                // Matches the videos.video_path column; longer would throw.
                'max:255',
            ],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_THUMBNAIL_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video_file.required' => 'Please choose a video file to upload.',
            'video_url.required' => 'Please provide the streaming or embed link.',
            'price.min' => 'A paid video needs a price of at least 1.',
        ];
    }
}
