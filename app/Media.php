<?php

namespace App;

use App\Util\Media\License;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Storage;
use Illuminate\Support\Facades\URL;

class Media extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $guarded = [];

    protected $casts = [
        'srcset' => 'array',
        'deleted_at' => 'datetime',
        'skip_optimize' => 'boolean',
        'replicated_at' => 'datetime',
    ];

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function url()
    {
        if ($this->cdn_url) {
            // return Storage::disk(config('filesystems.cloud'))->url($this->media_path);
            return $this->cdn_url;
        }

        if ($this->remote_media && $this->remote_url) {
            return $this->remote_url;
        }

        return url(URL::temporarySignedRoute(
            'storage.file',
            now()->addMinutes(60),
            ['file' => $this->media_path, 'user_id' => auth()->id()]
        ));
    }

    public function thumbnailUrl()
    {
        if ($this->thumbnail_url) {
            return $this->thumbnail_url;
        }

        if (! $this->remote_media && $this->thumbnail_path) {
            return url(URL::temporarySignedRoute(
                'storage.file',
                now()->addMinutes(60),
                ['file' => $this->thumbnail_path, 'user_id' => auth()->id()]
            ));
        }

        if (! $this->thumbnail_path && $this->cdn_url) {
            return $this->cdn_url;
        }

        if ($this->media_path && $this->mime && in_array($this->mime, ['image/jpeg', 'image/png'])) {
            return $this->remote_media || Str::startsWith($this->media_path, 'http') ?
                $this->media_path :
                url(URL::temporarySignedRoute(
                    'storage.file',
                    now()->addMinutes(60),
                    ['file' => $this->media_path, 'user_id' => auth()->id()]
                ));
        }

        return url(Storage::url('public/no-preview.png'));
    }

    public function thumb()
    {
        return $this->thumbnailUrl();
    }

    public function mimeType()
    {
        if (! $this->mime) {
            return;
        }

        return explode('/', $this->mime)[0];
    }

    public function activityVerb()
    {
        $verb = 'Image';
        switch ($this->mimeType()) {
            case 'audio':
                $verb = 'Audio';
                break;

            case 'image':
                $verb = 'Image';
                break;

            case 'video':
                $verb = 'Video';
                break;

            default:
                $verb = 'Document';
                break;
        }

        return $verb;
    }

    public function getMetadata()
    {
        return json_decode($this->metadata, true, 3);
    }

    public function getModel()
    {
        if (empty($this->metadata)) {
            return false;
        }
        $meta = $this->getMetadata();
        if ($meta && isset($meta['Model'])) {
            return $meta['Model'];
        }
    }

    public function getLicense()
    {
        $license = $this->license;

        if (! $license || strlen($license) > 2 || $license == 1) {
            return null;
        }

        if (! in_array($license, License::keys())) {
            return null;
        }

        $res = License::get()[$license];

        return [
            'id' => $res['id'],
            'title' => $res['title'],
            'url' => $res['url'],
        ];
    }
}
