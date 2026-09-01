<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * A receipt or invoice, belonging to whatever it was uploaded against - a
 * transaction or a settlement.
 *
 * Files live on the private disk and are streamed through an authenticated
 * route, never exposed under a guessable public URL.
 */
class Attachment extends Model
{
    // attachable_type and attachable_id are set by the relation, not by mass
    // assignment - a request must never be able to point a file at another
    // owner by posting the keys.
    protected $fillable = [
        'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function getReadableSizeAttribute(): string
    {
        $bytes = $this->size;

        foreach (['B', 'KB', 'MB'] as $unit) {
            if ($bytes < 1024 || $unit === 'MB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return $bytes.' B';
    }

    /**
     * Removes the file from storage as well as the row, so deleting an
     * attachment never leaves an orphan on disk.
     */
    public function deleteWithFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);

        $this->delete();
    }
}
