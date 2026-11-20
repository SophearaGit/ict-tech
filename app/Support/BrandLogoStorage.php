<?php

namespace App\Support;

use App\Exceptions\LogoNotSaved;
use ErrorException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

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
        $name = bin2hex(random_bytes(16)).'.'.$extension;

        try {
            File::ensureDirectoryExists($dir);
            $file->move($dir, $name);
        } catch (ErrorException|FileException $e) {
            // Typically: the folder does not exist yet and public/ is not writable by the web server's user.
            throw new LogoNotSaved("Could not save the logo into {$dir}: ".$e->getMessage(), 0, $e);
        }

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
