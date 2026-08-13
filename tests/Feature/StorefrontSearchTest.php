<?php

declare(strict_types=1);

use App\Models\Product;

it('finds a product by a word in its name', function (): void {
    Product::factory()->create(['name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts']]);
    Product::factory()->create(['name' => ['en' => 'Closing Night', 'nl' => 'Slotavond']]);

    $this->get(route('shop.index', ['q' => 'Fright']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.name', 'Stage Fright'));
});

it('finds a product by a word in its description', function (): void {
    Product::factory()->create([
        'name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts'],
        'description' => ['en' => 'A comedy for six actors.', 'nl' => 'Een komedie.'],
    ]);
    Product::factory()->create(['description' => ['en' => 'Something else entirely.', 'nl' => 'Iets anders.']]);

    $this->get(route('shop.index', ['q' => 'comedy']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 1));
});

it('searches the active locale and does not leak matches from the other one', function (): void {
    Product::factory()->create(['name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts']]);

    // Reading English, searching the Dutch title: no match.
    $this->get(route('shop.index', ['q' => 'Plankenkoorts']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 0));

    $this->post(route('locale.switch', 'nl'));

    $this->get(route('shop.index', ['q' => 'Plankenkoorts']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 1));
});

it('ignores case', function (): void {
    Product::factory()->create(['name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts']]);

    $this->get(route('shop.index', ['q' => 'stage fright']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 1));
});

it('never returns a draft product from a search', function (): void {
    Product::factory()->draft()->create(['name' => ['en' => 'Secret Draft', 'nl' => 'Concept']]);

    $this->get(route('shop.index', ['q' => 'Secret']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 0));
});

it('shows everything when the search is blank', function (): void {
    Product::factory()->count(3)->create();

    $this->get(route('shop.index', ['q' => '   ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 3));
});

it('sorts by price low to high', function (): void {
    Product::factory()->create(['price' => 3000, 'name' => ['en' => 'Dear', 'nl' => 'Duur']]);
    Product::factory()->create(['price' => 1000, 'name' => ['en' => 'Cheap', 'nl' => 'Goedkoop']]);

    $this->get(route('shop.index', ['sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.0.name', 'Cheap'));
});

it('sorts by price high to low', function (): void {
    Product::factory()->create(['price' => 1000, 'name' => ['en' => 'Cheap', 'nl' => 'Goedkoop']]);
    Product::factory()->create(['price' => 3000, 'name' => ['en' => 'Dear', 'nl' => 'Duur']]);

    $this->get(route('shop.index', ['sort' => 'price_desc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.0.name', 'Dear'));
});

it('sorts by newest', function (): void {
    Product::factory()->create(['name' => ['en' => 'Older', 'nl' => 'Ouder'], 'created_at' => now()->subWeek()]);
    Product::factory()->create(['name' => ['en' => 'Newer', 'nl' => 'Nieuwer'], 'created_at' => now()]);

    $this->get(route('shop.index', ['sort' => 'newest']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.0.name', 'Newer'));
});

it('falls back to the curated order for an unknown sort', function (): void {
    Product::factory()->create(['name' => ['en' => 'Second', 'nl' => 'Tweede'], 'sort_order' => 2]);
    Product::factory()->create(['name' => ['en' => 'First', 'nl' => 'Eerste'], 'sort_order' => 1]);

    $this->get(route('shop.index', ['sort' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.0.name', 'First'));
});

it('hands the filters back so the state can live in the url', function (): void {
    $this->get(route('shop.index', ['q' => 'fright', 'sort' => 'newest']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.q', 'fright')
            ->where('filters.sort', 'newest'));
});

it('reports the default sort when none is given', function (): void {
    $this->get(route('shop.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'default')->where('filters.q', ''));
});

it('combines a search with a sort', function (): void {
    Product::factory()->create(['name' => ['en' => 'Fright Night', 'nl' => 'A'], 'price' => 3000]);
    Product::factory()->create(['name' => ['en' => 'Stage Fright', 'nl' => 'B'], 'price' => 1000]);
    Product::factory()->create(['name' => ['en' => 'Something Else', 'nl' => 'C'], 'price' => 500]);

    $this->get(route('shop.index', ['q' => 'Fright', 'sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products', 2)
            ->where('products.0.name', 'Stage Fright'));
});
