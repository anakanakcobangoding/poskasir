<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

test('a product upload stores a durable image path and can be loaded by the cashier', function () {
    $category = Category::factory()->create();
    $image = UploadedFile::fake()->image('menu.png');
    $bytes = file_get_contents($image->getRealPath());

    $this->post(route('products.store'), ['category_id' => $category->id, 'name' => 'Menu Foto', 'price' => 10000, 'image' => $image])
        ->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

    $product = Product::sole();
    expect($product->image)->toStartWith('products/');
    Storage::disk('public')->assertExists($product->image);
    $this->actingAs(User::factory()->kasir()->create());
    $response = $this->get('/products/'.$product->id.'/image')->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->streamedContent())->toBe($bytes);
});

test('replacing a product image only removes the old image after the replacement is saved', function () {
    Storage::disk('public')->put('products/old.png', 'old');
    $product = Product::factory()->create(['image' => 'products/old.png']);

    $this->put(route('products.update', $product), ['category_id' => $product->category_id, 'name' => $product->name, 'price' => $product->price, 'image' => UploadedFile::fake()->image('new.png')])
        ->assertSessionHasNoErrors()->assertRedirect(route('products.index'));

    Storage::disk('public')->assertExists($product->fresh()->image);
    Storage::disk('public')->assertMissing('products/old.png');
});

test('invalid image uploads leave the existing product and file intact', function () {
    Storage::disk('public')->put('products/keep.png', 'existing');
    $product = Product::factory()->create(['image' => 'products/keep.png']);

    $this->put(route('products.update', $product), ['category_id' => $product->category_id, 'name' => 'Changed', 'price' => 10000, 'image' => UploadedFile::fake()->create('bad.php', 10, 'text/plain')])
        ->assertSessionHasErrors('image')->assertSessionMissing('success');

    expect($product->fresh()->image)->toBe('products/keep.png');
    expect($product->fresh()->name)->toBe($product->name);
    Storage::disk('public')->assertExists('products/keep.png');
});

test('product images reject forged extensions, invalid content, and oversized uploads', function (Closure $image) {
    $category = Category::factory()->create();

    $this->post(route('products.store'), ['category_id' => $category->id, 'name' => 'Rejected', 'price' => 10000, 'image' => $image()])
        ->assertSessionHasErrors('image')->assertSessionMissing('success');

    $this->assertDatabaseCount('products', 0);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'executable extension' => [fn () => UploadedFile::fake()->image('image.php')],
    'invalid image content' => [function (): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'invalid-product-image-');
        file_put_contents($path, '<?php echo "invalid";');
        test()->beforeApplicationDestroyed(fn (): bool => unlink($path));

        return new UploadedFile($path, 'image.png', 'image/png', null, true);
    }],
    'over two megabytes' => [fn () => UploadedFile::fake()->image('large.png')->size(2049)],
]);

test('a database failure preserves the old product image and cleans up the replacement', function () {
    Exceptions::fake();
    Storage::disk('public')->put('products/keep.png', 'existing');
    $product = Product::factory()->create(['image' => 'products/keep.png']);
    Product::updating(function (): void {
        throw new RuntimeException('Simulated database write failure');
    });

    try {
        $this->put(route('products.update', $product), ['category_id' => $product->category_id, 'name' => 'Changed', 'price' => 10000, 'image' => UploadedFile::fake()->image('new.png')])
            ->assertServerError()->assertSessionMissing('success');

        expect($product->fresh()->image)->toBe('products/keep.png');
        expect(Storage::disk('public')->allFiles())->toBe(['products/keep.png']);
        Exceptions::assertReported(RuntimeException::class);
    } finally {
        Event::forget('eloquent.updating: '.Product::class);
    }
});
