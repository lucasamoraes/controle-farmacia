<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Product;
use App\Models\PurchaseList;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_supplier_can_be_added_with_different_quotation_names(): void
    {
        [$company, $user] = $this->companyWithUser();
        $supplier = $this->merchandiseSupplier($company);
        $quotation = $this->quotation($company, $user);

        $this->actingAs($user)->post("/cotacoes/{$quotation->id}/fornecedores", [
            'supplier_id' => $supplier->id,
            'display_name' => 'Distribuidora - Joao',
        ])->assertRedirect("/cotacoes/{$quotation->id}");

        $this->actingAs($user)->post("/cotacoes/{$quotation->id}/fornecedores", [
            'supplier_id' => $supplier->id,
            'display_name' => 'Distribuidora - Maria',
        ])->assertRedirect("/cotacoes/{$quotation->id}");

        $this->assertDatabaseCount('quotation_suppliers', 2);
        $this->assertDatabaseHas('quotation_suppliers', [
            'quotation_id' => $quotation->id,
            'supplier_id' => $supplier->id,
            'display_name' => 'Distribuidora - Joao',
        ]);
        $this->assertDatabaseHas('quotation_suppliers', [
            'quotation_id' => $quotation->id,
            'supplier_id' => $supplier->id,
            'display_name' => 'Distribuidora - Maria',
        ]);
    }

    public function test_removing_quotation_participant_removes_only_its_prices(): void
    {
        [$company, $user] = $this->companyWithUser();
        $supplier = $this->merchandiseSupplier($company);
        $quotation = $this->quotation($company, $user);
        $first = $quotation->participants()->create(['supplier_id' => $supplier->id, 'display_name' => 'Joao']);
        $second = $quotation->participants()->create(['supplier_id' => $supplier->id, 'display_name' => 'Maria']);
        $item = $quotation->purchaseList->items()->first();

        $quotation->prices()->create([
            'purchase_list_item_id' => $item->id,
            'quotation_supplier_id' => $first->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 10,
        ]);
        $quotation->prices()->create([
            'purchase_list_item_id' => $item->id,
            'quotation_supplier_id' => $second->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 12,
        ]);

        $this->actingAs($user)
            ->delete("/cotacoes/{$quotation->id}/participantes/{$first->id}")
            ->assertRedirect("/cotacoes/{$quotation->id}");

        $this->assertDatabaseMissing('quotation_suppliers', ['id' => $first->id]);
        $this->assertDatabaseMissing('quotation_prices', ['quotation_supplier_id' => $first->id]);
        $this->assertDatabaseHas('quotation_prices', ['quotation_supplier_id' => $second->id]);
    }

    public function test_finance_user_can_remove_item_from_quotation_map(): void
    {
        [$company, $user] = $this->companyWithUser();
        $supplier = $this->merchandiseSupplier($company);
        $quotation = $this->quotation($company, $user);
        $participant = $quotation->participants()->create(['supplier_id' => $supplier->id]);
        $item = $quotation->purchaseList->items()->first();

        $quotation->prices()->create([
            'purchase_list_item_id' => $item->id,
            'quotation_supplier_id' => $participant->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 10,
        ]);

        $this->actingAs($user)
            ->delete("/cotacoes/{$quotation->id}/itens/{$item->id}")
            ->assertRedirect("/cotacoes/{$quotation->id}");

        $this->assertDatabaseMissing('purchase_list_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('quotation_prices', ['purchase_list_item_id' => $item->id]);
    }

    public function test_zero_quantity_is_rejected_when_saving_quotation_prices(): void
    {
        [$company, $user] = $this->companyWithUser();
        $supplier = $this->merchandiseSupplier($company);
        $quotation = $this->quotation($company, $user);
        $participant = $quotation->participants()->create(['supplier_id' => $supplier->id]);
        $item = $quotation->purchaseList->items()->first();

        $quotation->prices()->create([
            'purchase_list_item_id' => $item->id,
            'quotation_supplier_id' => $participant->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 10,
        ]);

        $this->actingAs($user)
            ->put("/cotacoes/{$quotation->id}/precos", [
                'quantities' => [$item->id => 0],
                'prices' => [$item->id => [$participant->id => 10]],
            ])
            ->assertSessionHasErrors('quantities');

        $this->assertDatabaseHas('purchase_list_items', ['id' => $item->id]);
        $this->assertDatabaseHas('quotation_prices', ['purchase_list_item_id' => $item->id]);
    }

    private function companyWithUser(): array
    {
        $company = Company::create([
            'name' => 'Farmacia Teste',
            'trade_name' => 'Farmacia Teste',
        ]);
        $user = User::factory()->create();

        $company->users()->attach($user->id, ['role' => 'finance']);

        return [$company, $user];
    }

    private function merchandiseSupplier(Company $company): Supplier
    {
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
        ]);

        return Supplier::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'name' => 'Distribuidora Teste',
            'is_active' => true,
        ]);
    }

    private function quotation(Company $company, User $user): Quotation
    {
        $product = Product::create([
            'company_id' => $company->id,
            'description' => 'DIPIRONA 500 MG',
            'class' => 'GENERICOS',
            'last_purchase_price' => 5,
            'is_active' => true,
        ]);
        $list = PurchaseList::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'title' => 'Lista teste',
            'status' => 'quoting',
        ]);
        $list->items()->create([
            'product_id' => $product->id,
            'description' => $product->description,
            'quantity' => 1,
            'unit' => 'un',
        ]);

        return Quotation::create([
            'company_id' => $company->id,
            'purchase_list_id' => $list->id,
            'created_by' => $user->id,
            'status' => 'open',
        ]);
    }
}
