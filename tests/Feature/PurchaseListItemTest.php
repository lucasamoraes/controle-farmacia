<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseListItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_same_product_updates_existing_list_item(): void
    {
        [$company, $user] = $this->companyWithUser();
        $product = Product::create([
            'company_id' => $company->id,
            'description' => 'LUFTAL 40 MG',
            'class' => 'ETICOS',
            'last_purchase_price' => 0,
            'is_active' => true,
        ]);
        $list = PurchaseList::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'title' => 'Lista teste',
            'status' => 'open',
        ]);

        $this->actingAs($user)->post("/listas-compras/{$list->id}/itens", [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'un',
        ])->assertRedirect("/listas-compras/{$list->id}");

        $this->actingAs($user)->post("/listas-compras/{$list->id}/itens", [
            'product_id' => $product->id,
            'quantity' => 3,
            'unit' => 'un',
        ])->assertRedirect("/listas-compras/{$list->id}");

        $this->assertDatabaseCount('purchase_list_items', 1);
        $this->assertDatabaseHas('purchase_list_items', [
            'purchase_list_id' => $list->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_updating_list_item_can_update_product_last_purchase_price(): void
    {
        [$company, $user] = $this->companyWithUser();
        $product = Product::create([
            'company_id' => $company->id,
            'description' => 'DIPIRONA 500 MG',
            'class' => 'GENERICOS',
            'last_purchase_price' => 4.50,
            'is_active' => true,
        ]);
        $list = PurchaseList::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'title' => 'Lista teste',
            'status' => 'open',
        ]);
        $item = $list->items()->create([
            'product_id' => $product->id,
            'description' => $product->description,
            'quantity' => 1,
            'unit' => 'un',
        ]);

        $this->actingAs($user)->put("/listas-compras/itens/{$item->id}", [
            'quantity' => 1,
            'unit' => 'un',
            'last_purchase_price' => 7.25,
        ])->assertRedirect("/listas-compras/{$list->id}");

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'last_purchase_price' => 7.25,
        ]);
    }

    private function companyWithUser(): array
    {
        $company = Company::create([
            'name' => 'Farmacia Teste',
            'trade_name' => 'Farmacia Teste',
        ]);
        $user = User::factory()->create();

        $company->users()->attach($user->id, ['role' => 'buyer']);

        return [$company, $user];
    }
}
