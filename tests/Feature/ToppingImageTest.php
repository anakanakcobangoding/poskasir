<?php

use App\Models\Topping;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an admin can upload and reload a topping image from permanent storage', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $image = UploadedFile::fake()->image('topping.png');
    $bytes = file_get_contents($image->getRealPath());

    $this->post(route('toppings.store'), ['name' => 'Telur Foto', 'price' => 3000, 'hpp' => 1000, 'image' => $image])->assertSessionHasNoErrors()->assertRedirect(route('toppings.index'));

    $topping = Topping::sole();
    expect($topping->image)->toStartWith('toppings/');
    Storage::disk('public')->assertExists($topping->image);
    $this->get(route('toppings.edit', $topping))->assertOk()->assertSee('/toppings/'.$topping->id.'/image');
    $this->get(route('toppings.index'))->assertOk()->assertSee('/toppings/'.$topping->id.'/image');
    $response = $this->get('/toppings/'.$topping->id.'/image')->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->streamedContent())->toBe($bytes);
});
