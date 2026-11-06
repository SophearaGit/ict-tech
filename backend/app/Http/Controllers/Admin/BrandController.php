<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\BrandLogoStorage;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::withCount('products')->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Brand $brand) => $this->transform($brand));

        return response()->json($brands);
    }

    /**
     * Add a brand, with an optional logo (multipart `logo`; a JSON body with just `name` still works).
     * The row and the file are saved together or not at all: if anything fails after the file was
     * written, the file is deleted again and the error continues.
     */
    public function store(Request $request, BrandLogoStorage $logos): JsonResponse
    {
        $validated = $request->validate([
            'name' => $this->nameRules(),
            'logo' => $this->logoRules(),
        ], $this->logoMessages());

        $stored = null;
        try {
            $brand = DB::transaction(function () use ($validated, $request, $logos, &$stored) {
                $brand = Brand::create(['name' => $validated['name']]);

                if ($request->hasFile('logo')) {
                    $stored = $logos->store($request->file('logo'));
                    $brand->forceFill(['logo' => $stored])->save();
                }

                return $brand;
            });
        } catch (Throwable $e) {
            $logos->delete($stored);
            throw $e;
        }

        // refresh(): a new row gets its column defaults (is_active = true) from the database, not from this object.
        return response()->json($this->transform($brand->refresh()->loadCount('products')), 201);
    }

    /**
     * Rename and/or switch ON/OFF. Each field is validated only when it is sent, so the switch
     * never has to re-send the name (and a rename never touches the switch).
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', ...$this->nameRules($brand)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! array_key_exists('name', $validated) && ! array_key_exists('is_active', $validated)) {
            throw ValidationException::withMessages(['name' => 'Send a name or an on/off setting.']);
        }

        if (array_key_exists('name', $validated)) {
            $brand->name = $validated['name'];
        }
        if (array_key_exists('is_active', $validated)) {
            $brand->is_active = $validated['is_active'];
        }
        $brand->save();

        return response()->json($this->transform($brand->loadCount('products')));
    }

    public function destroy(Brand $brand, BrandLogoStorage $logos): JsonResponse
    {
        $count = $brand->products()->count();

        if ($count > 0) {
            return response()->json([
                'ok' => false,
                'error' => "{$brand->name} still has {$count} ".Str::plural('product', $count).'. Move or delete them first.',
            ], 422);
        }

        $brand->delete();
        $logos->delete($brand->logo); // after the row: a failed delete must never lose the logo

        return response()->json(['ok' => true]);
    }

    /**
     * Replace the logo. Order matters: save the new file, point the row at it, and only then delete
     * the old file, so a failure at any step leaves at worst an orphan file, never a brand whose logo is missing.
     */
    public function storeLogo(Request $request, Brand $brand, BrandLogoStorage $logos): JsonResponse
    {
        $request->validate(['logo' => $this->logoRules(required: true)], $this->logoMessages());

        $old = $brand->logo;
        $new = $logos->store($request->file('logo'));

        try {
            $brand->forceFill(['logo' => $new])->save();
        } catch (Throwable $e) {
            $logos->delete($new);
            throw $e;
        }

        $logos->delete($old);

        return response()->json($this->transform($brand->loadCount('products')));
    }

    public function destroyLogo(Brand $brand, BrandLogoStorage $logos): JsonResponse
    {
        $old = $brand->logo;
        $brand->forceFill(['logo' => null])->save();
        $logos->delete($old);

        return response()->json($this->transform($brand->loadCount('products')));
    }

    /**
     * Rules for a new or renamed brand name. Uniqueness ignores case (and the brand being
     * renamed) and is checked here with lower(name) so it behaves the same on MySQL and on
     * the SQLite test database. Rename rules apply to the NEW name only: a legacy name the
     * migration copied unchanged is never re-validated.
     */
    private function nameRules(?Brand $ignoring = null): array
    {
        return [
            'bail',
            'required',
            'string',
            'max:50',
            'regex:/^[\p{L}\p{N} .\-]+$/u',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoring) {
                $taken = Brand::whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                    ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->id))
                    ->exists();

                if ($taken) {
                    $fail('That brand already exists.');
                }
            },
        ];
    }

    /**
     * A logo must be a real PNG, JPG or WebP (the type comes from the file's content, not its name),
     * at most 800 KB (the live nginx refuses request bodies over 1 MB by default) and between
     * 32 and 4000 pixels each way. SVG is not allowed: it can carry scripts.
     */
    private function logoRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'bail', 'file', 'mimes:png,jpg,jpeg,webp', 'max:800', 'dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000'];
    }

    private function logoMessages(): array
    {
        $refused = 'The logo must be a PNG, JPG or WebP image under 800 KB.';

        return [
            'logo.required' => 'Choose a logo image first.',
            'logo.file' => $refused,
            'logo.uploaded' => $refused, // Laravel's own message when PHP rejected the upload (for example over its size limit)
            'logo.mimes' => $refused,
            'logo.max' => $refused,
            'logo.dimensions' => 'The logo must be between 32 and 4000 pixels wide and tall.',
        ];
    }

    private function transform(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'productCount' => $brand->products_count,
            'isActive' => (bool) $brand->is_active,
            'logoUrl' => $brand->logo_url,
        ];
    }
}
