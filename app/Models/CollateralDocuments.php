<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollateralDocuments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_collateral_id',
        'document_type',
        'file_path',
        'mime_type',
        'file_size',
        'original_filename',
        'is_verified',
        'verified_by',
        'verified_at',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function customerCollateral()
    {
        return $this->belongsTo(CustomerCollaterals::class, 'customer_collateral_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full storage path for the file
     */
    public function getFullPathAttribute()
    {
        return storage_path('app/' . $this->file_path);
    }

    /**
     * Check if file exists in storage
     */
    public function fileExists()
    {
        return \Storage::disk('private')->exists($this->file_path);
    }

    public function locateStorage()
    {
        if (\Storage::disk('private')->exists($this->file_path)) {
            return ['private', $this->file_path];
        }
        if (\Storage::exists($this->file_path)) {
            return ['local', $this->file_path];
        }
        return [null, null];
    }

    /**
     * Get file size in human readable format
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get document type display name
     */
    public function getDocumentTypeDisplayAttribute()
    {
        $types = [
            'title_deed' => 'Title Deed',
            'logbook' => 'Vehicle Logbook',
            'photo' => 'Asset Photo',
            'valuation_report' => 'Valuation Report',
            'insurance' => 'Insurance Policy',
            'ownership_proof' => 'Ownership Proof',
            'other' => 'Other Document',
        ];

        return $types[$this->document_type] ?? ucfirst(str_replace('_', ' ', $this->document_type));
    }
}
