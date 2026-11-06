<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Support\BrandLogoStorage;
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

    /** A brand that already has a stored logo (saved through the real storage class). */
    private function brandWithLogo(string $name = 'Huawei'): Brand
    {
        $brand = $this->makeBrand($name);
        $brand->forceFill(['logo' => app(BrandLogoStorage::class)->store($this->fakeLogo())])->save();

        return $brand;
    }

    private function putLogo(Brand $brand, ?UploadedFile $logo)
    {
        return $this->actingAsAdmin()->post(route('admin-brands.logo.store', $brand), array_filter(['logo' => $logo]), ['Accept' => 'application/json']);
    }

    public function test_a_logo_can_be_replaced_and_the_old_file_is_deleted(): void
    {
        $brand = $this->brandWithLogo();
        $old = $brand->logo;

        $response = $this->putLogo($brand, $this->fakeLogo('new.png', 80, 80))->assertOk();

        $files = $this->logoFilesIn($this->logoDir);
        $this->assertCount(1, $files);
        $this->assertNotSame($old, $files[0]);
        $this->assertSame(asset('uploads/brands/'.$files[0]), $response->json('logoUrl'));
        $this->assertSame($files[0], $brand->fresh()->logo);
    }

    public function test_replacing_needs_a_file(): void
    {
        $brand = $this->brandWithLogo();

        $this->putLogo($brand, null)->assertUnprocessable()->assertJsonValidationErrors(['logo']);
        $this->assertSame([$brand->logo], $this->logoFilesIn($this->logoDir));
    }

    public function test_a_refused_replacement_keeps_the_old_logo(): void
    {
        $brand = $this->brandWithLogo();
        $old = $brand->logo;

        $this->putLogo($brand, $this->uploadWithContent('just some text, not an image', 'x.png'))
            ->assertUnprocessable()->assertJsonValidationErrors(['logo']);

        $this->assertSame([$old], $this->logoFilesIn($this->logoDir));
        $this->assertSame($old, $brand->fresh()->logo);
    }

    public function test_a_logo_can_be_removed(): void
    {
        $brand = $this->brandWithLogo();

        $this->actingAsAdmin()->deleteJson(route('admin-brands.logo.destroy', $brand))
            ->assertOk()->assertJsonPath('logoUrl', null);

        $this->assertSame([], $this->logoFilesIn($this->logoDir));
        $this->assertNull($brand->fresh()->logo);
    }

    public function test_a_logo_whose_file_is_already_gone_can_be_replaced_removed_and_its_brand_deleted(): void
    {
        $vanish = fn (Brand $brand) => unlink($this->logoDir.DIRECTORY_SEPARATOR.$brand->logo);

        $replaced = $this->brandWithLogo('Alpha');
        $vanish($replaced);
        $this->putLogo($replaced, $this->fakeLogo())->assertOk();

        $removed = $this->brandWithLogo('Bravo');
        $vanish($removed);
        $this->actingAsAdmin()->deleteJson(route('admin-brands.logo.destroy', $removed))->assertOk()->assertJsonPath('logoUrl', null);

        $deleted = $this->brandWithLogo('Charlie');
        $vanish($deleted);
        $this->actingAsAdmin()->deleteJson(route('admin-brands.destroy', $deleted))->assertOk()->assertJson(['ok' => true]);
        $this->assertNull(Brand::find($deleted->id));
    }

    public function test_deleting_a_brand_deletes_its_logo_file(): void
    {
        $brand = $this->brandWithLogo();
        $this->assertCount(1, $this->logoFilesIn($this->logoDir));

        $this->actingAsAdmin()->deleteJson(route('admin-brands.destroy', $brand))->assertOk();

        $this->assertSame([], $this->logoFilesIn($this->logoDir));
    }

    public function test_a_brand_with_products_keeps_its_logo_when_the_delete_is_refused(): void
    {
        $brand = $this->brandWithLogo();
        $this->makeProduct(['brand_id' => $brand->id]);

        $this->actingAsAdmin()->deleteJson(route('admin-brands.destroy', $brand))->assertUnprocessable();

        $this->assertSame([$brand->logo], $this->logoFilesIn($this->logoDir));
    }

    public function test_renaming_a_brand_keeps_its_logo(): void
    {
        $brand = $this->brandWithLogo('huawei');

        $this->actingAsAdmin()->putJson(route('admin-brands.update', $brand), ['name' => 'Huawei'])
            ->assertOk()->assertJsonPath('logoUrl', asset('uploads/brands/'.$brand->logo));
        $this->assertSame([$brand->logo], $this->logoFilesIn($this->logoDir));
    }

    public function test_guests_and_customers_are_redirected_for_the_logo_routes(): void
    {
        $brand = $this->makeBrand('Huawei');

        foreach ([null, $this->makeCustomer()] as $who) {
            $client = $who ? $this->actingAs($who) : $this;
            $client->postJson(route('admin-brands.logo.store', $brand), [])->assertRedirect(route('admin-login'));
            $client->deleteJson(route('admin-brands.logo.destroy', $brand))->assertRedirect(route('admin-login'));
        }
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
