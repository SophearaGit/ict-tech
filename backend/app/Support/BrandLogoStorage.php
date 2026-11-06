<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Saves and removes brand logo files. The folder is `config('shop.brand_logos.dir')`, read on every
 * call so tests can point it at a temporary folder. Only the generated file name is ever stored in
 * the database, never a path and never the name the visitor's file had.
 */
class BrandLogoStorage
{
    /** What a logo may be, by the type PHP detects from the file's content. */
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /** Save the uploaded logo under a random name and return that name (for example `3f9c…e1.png`). */
    public function store(UploadedFile $file): string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()]
            ?? throw new InvalidArgumentException('Unsupported logo type: '.$file->getMimeType());

        $dir = config('shop.brand_logos.dir');
        File::ensureDirectoryExists($dir);

        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($dir, $name);

        return $name;
    }

    /** Delete a stored logo. Nothing to do (and no error) for null or a file that is already gone. */
    public function delete(?string $name): void
    {
        if ($name === null || $name === '') {
            return;
        }

        // basename(): the stored value can never point outside the logo folder.
        File::delete(config('shop.brand_logos.dir').DIRECTORY_SEPARATOR.basename($name));
    }
}
