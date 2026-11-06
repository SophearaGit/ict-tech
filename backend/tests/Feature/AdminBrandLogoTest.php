<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesLogoFiles;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AdminBrandLogoTest extends TestCase
{
    use MakesLogoFiles, MakesShopData, RefreshDatabase;

    private const REFUSED = 'The logo must be a PNG, JPG or WebP image under 800 KB.';

    private ?Admin $admin = null;

    private string $logoDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logoDir = $this->useTemporaryLogoFolder();
    }

    /** One admin per test, even when a test makes several requests. */
    private function actingAsAdmin(): static
    {
        $this->admin ??= $this->makeAdmin();

        return $this->actingAs($this->admin, 'admin');
    }

    /** A multipart request (a logo travels as a file, so this cannot be postJson). */
    private function addBrand(string $name, ?UploadedFile $logo = null)
    {
        return $this->actingAsAdmin()->post(route('admin-brands.store'), array_filter(['name' => $name, 'logo' => $logo]), ['Accept' => 'application/json']);
    }

    public function test_a_brand_can_be_added_with_a_logo(): void
    {
        $response = $this->addBrand('Huawei', $this->fakeLogo())->assertCreated();

        $files = $this->logoFilesIn($this->logoDir);
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}\.png$/', $files[0]);
        $this->assertSame(asset('uploads/brands/'.$files[0]), $response->json('logoUrl'));
        $this->assertSame($files[0], Brand::firstOrFail()->logo);
    }

    public function test_a_brand_can_still_be_added_without_a_logo(): void
    {
        $this->addBrand('Huawei')->assertCreated()->assertJsonPath('logoUrl', null);

        $this->assertSame([], $this->logoFilesIn($this->logoDir));
    }

    public static function disguisedFiles(): array
    {
        return [
            'an SVG' => ['<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><script>alert(1)</script></svg>'],
            'a PHP script' => ['<?php echo "hi";'],
            'a GIF' => ["GIF89a\x40\x00\x40\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x40\x00\x40\x00\x00\x02\x02D\x01\x00;"],
        ];
    }

    #[DataProvider('disguisedFiles')]
    public function test_the_logo_is_checked_by_content_not_by_name(string $content): void
    {
        $this->addBrand('Huawei', $this->uploadWithContent($content))
            ->assertUnprocessable()->assertJsonValidationErrors(['logo']);

        $this->assertSame(0, Brand::count());
        $this->assertSame([], $this->logoFilesIn($this->logoDir));
    }

    public static function wrongSizes(): array
    {
        return [['over 800 KB'], ['under 32 px'], ['over 4000 px wide']];
    }

    #[DataProvider('wrongSizes')]
    public function test_wrong_sized_logos_are_refused(string $kind): void
    {
        $file = match ($kind) {
            'over 800 KB' => UploadedFile::fake()->create('big.png', 900),
            'under 32 px' => $this->fakeLogo('s.png', 16, 16),
            'over 4000 px wide' => $this->fakeLogo('w.png', 4001, 32),
        };

        $this->addBrand('Huawei', $file)->assertUnprocessable()->assertJsonValidationErrors(['logo']);

        $this->assertSame(0, Brand::count());
        $this->assertSame([], $this->logoFilesIn($this->logoDir));
    }

    public function test_a_file_php_rejected_for_size_gets_a_plain_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'php-upload');
        $rejected = new UploadedFile($path, 'logo.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

        $this->addBrand('Huawei', $rejected)
            ->assertUnprocessable()->assertJsonPath('errors.logo.0', self::REFUSED);

        @unlink($path);
        $this->assertSame(0, Brand::count());
    }
}
