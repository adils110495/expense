<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TransactionAttachment extends Model
{
    protected $fillable = [
        'transaction_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
