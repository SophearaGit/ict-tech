<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Brand-logo fixtures without the `gd` extension (UploadedFile::fake()->image() needs it, and neither
 * this PC's PHP nor necessarily the live server has it). The PNGs are built by hand, so a test can ask
 * for any pixel size, and logos are written to a throwaway folder, never to the real public/uploads.
 */
trait MakesLogoFiles
{
    /** A valid, tiny PNG of the given pixel size (solid colour, so it compresses to a few hundred bytes). */
    protected function pngBytes(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $scanlines = str_repeat("\0".str_repeat("\x40\x80\xc0", $width), $height);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($scanlines))
            .$chunk('IEND', '');
    }

    protected function fakeLogo(string $name = 'logo.png', int $width = 64, int $height = 64): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngBytes($width, $height));
    }

    /**
     * A REAL upload with the given bytes, claiming to be a PNG. UploadedFile::fake() cannot stand in for
     * this: the fake reports its type from the file NAME, so an SVG called logo.png would look like a PNG.
     * A real UploadedFile is typed from its content, which is what production does and what these tests prove.
     */
    protected function uploadWithContent(string $content, string $name = 'logo.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo-upload');
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile($path, $name, 'image/png', UPLOAD_ERR_OK, true);
    }

    /** Point the logo folder at a fresh temp folder for this test and delete it afterwards. */
    protected function useTemporaryLogoFolder(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'brand-logos-'.bin2hex(random_bytes(6));
        config(['shop.brand_logos.dir' => $dir]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));

        return $dir;
    }

    /** File names in a logo folder (empty when the folder was never created). */
    protected function logoFilesIn(string $dir): array
    {
        return is_dir($dir) ? array_map('basename', File::files($dir)) : [];
    }
}
