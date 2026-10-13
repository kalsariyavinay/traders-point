<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RedirectsOrJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVideoRequest;
use App\Http\Requests\Admin\UpdateVideoRequest;
use App\Models\Category;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VideoController extends Controller
{
    use RedirectsOrJson;

    /** Uploaded videos stay on the private disk; only thumbnails are public. */
    private const VIDEO_DISK = 'local';

    private const THUMBNAIL_DISK = 'public';

    /** Wide enough for a retina catalogue card; anything larger is waste. */
    private const THUMBNAIL_MAX_WIDTH = 1280;

    private const THUMBNAIL_QUALITY = 82;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $videos = Video::query()
            ->with('category')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
            ))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.videos.index', [
            'videos' => $videos,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.videos.create', [
            'video' => new Video(['is_free' => true, 'price' => 0]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(StoreVideoRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $video = new Video([
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => Video::uniqueSlug($data['title']),
            'description' => $data['description'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'price' => $data['price'],
            'is_free' => $data['is_free'],
            'status' => $data['status'],
            'video_path' => $this->resolveVideoPath($request),
        ]);

        if ($request->hasFile('thumbnail')) {
            $video->thumbnail = $this->storeThumbnail($request);
        }

        $video->save();

        return $this->finish($request, route('admin.videos.index'), "\"{$video->title}\" was created.");
    }

    public function edit(Video $video): View
    {
        return view('admin.videos.edit', [
            'video' => $video,
            'categories' => $this->categories(),
        ]);
    }

    public function update(UpdateVideoRequest $request, Video $video): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $previousPath = $video->video_path;
        $newPath = $this->resolveVideoPath($request, $video);

        if ($request->hasFile('thumbnail')) {
            $this->deleteThumbnail($video->thumbnail);
            $video->thumbnail = $this->storeThumbnail($request);
        }

        $video->fill([
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'price' => $data['price'],
            'is_free' => $data['is_free'],
            'status' => $data['status'],
            'video_path' => $newPath,
        ]);

        if ($video->isDirty('title')) {
            $video->slug = Video::uniqueSlug($data['title'], $video->id);
        }

        $video->save();

        if ($newPath !== $previousPath) {
            $this->deleteStoredVideo($previousPath);
        }

        return $this->finish($request, route('admin.videos.index'), "\"{$video->title}\" was updated.");
    }

    public function destroy(Video $video): RedirectResponse
    {
        // orders.video_id cascades on delete, so a sold video cannot be removed
        // without destroying its payment records. Unpublish it instead.
        if ($video->orders()->successful()->exists()) {
            return to_route('admin.videos.index')
                ->with('error', "\"{$video->title}\" has paid orders and cannot be deleted. Set it to draft instead.");
        }

        $title = $video->title;

        $this->deleteStoredVideo($video->video_path);
        $this->deleteThumbnail($video->thumbnail);
        $video->delete();

        return to_route('admin.videos.index')
            ->with('success', "\"{$title}\" was deleted.");
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function categories()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Either a freshly uploaded file's path or the embed/streaming URL. On update
     * with an empty file input, whatever is already stored is kept.
     */
    private function resolveVideoPath(Request $request, ?Video $video = null): string
    {
        if ($request->input('video_source') === 'link') {
            return (string) $request->input('video_url');
        }

        if ($request->hasFile('video_file')) {
            return $request->file('video_file')->store('videos', self::VIDEO_DISK);
        }

        return (string) $video?->video_path;
    }

    private function storeThumbnail(Request $request): string
    {
        $path = $request->file('thumbnail')->store('thumbnails', self::THUMBNAIL_DISK);

        $this->downscaleThumbnail($path);

        return $path;
    }

    /**
     * Thumbnails arrive straight off a phone or a stock site -- 2580px wide and
     * over a megabyte -- to fill a 400px card. Shrinking on the way in is the
     * single biggest weight saving on the catalogue page, and it only has to
     * happen once per upload.
     *
     * Deliberately forgiving: if GD is unavailable or the file is not an image
     * we can decode, the original is kept. A heavy thumbnail is a much smaller
     * problem than a lost upload.
     */
    private function downscaleThumbnail(string $path): void
    {
        if (! function_exists('imagescale')) {
            return;
        }

        $disk = Storage::disk(self::THUMBNAIL_DISK);
        $original = $disk->get($path);
        $size = @getimagesizefromstring($original);

        if (! $size || $size[0] <= self::THUMBNAIL_MAX_WIDTH) {
            return;
        }

        $image = @imagecreatefromstring($original);

        if (! $image) {
            return;
        }

        $scaled = imagescale($image, self::THUMBNAIL_MAX_WIDTH);
        imagedestroy($image);

        if (! $scaled) {
            return;
        }

        // imagescale drops the alpha channel flags, so a transparent PNG or
        // WebP would come back with a black background without these.
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        ob_start();

        match ($size[2]) {
            IMAGETYPE_PNG => imagepng($scaled, null, 6),
            IMAGETYPE_WEBP => imagewebp($scaled, null, self::THUMBNAIL_QUALITY),
            default => imagejpeg($scaled, null, self::THUMBNAIL_QUALITY),
        };

        $encoded = (string) ob_get_clean();
        imagedestroy($scaled);

        // Only keep the new file if it is actually smaller.
        if ($encoded !== '' && strlen($encoded) < strlen($original)) {
            $disk->put($path, $encoded);
        }
    }

    private function deleteStoredVideo(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://'])) {
            Storage::disk(self::VIDEO_DISK)->delete($path);
        }
    }

    private function deleteThumbnail(?string $path): void
    {
        if ($path) {
            Storage::disk(self::THUMBNAIL_DISK)->delete($path);
        }
    }
}
