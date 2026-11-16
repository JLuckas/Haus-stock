<?php

namespace Tests\Feature\Web;

use App\Models\Expense;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function expense(array $tags): Expense
    {
        $this->post(route('expenses.store'), [
            'kind' => 'general', 'spent_on' => today()->toDateString(), 'category' => 'maintenance', 'amount' => '100', 'tags' => $tags,
        ])->assertSessionHasNoErrors();

        return Expense::query()->latest('id')->firstOrFail();
    }

    public function test_tags_can_be_created_ahead_and_are_offered_in_the_form(): void
    {
        $this->post(route('expenses.tags.store'), ['name' => '#Fornecedor', 'color' => 'teal'])->assertSessionHas('success');
        $this->post(route('expenses.tags.store'), ['name' => 'fornecedor'])->assertSessionHas('error'); // same tag

        $tag = Tag::query()->sole();
        $this->assertSame(['Fornecedor', 'fornecedor', 'teal'], [$tag->name, $tag->slug, $tag->color]);

        $html = $this->get(route('expenses.index', 'general'))->assertOk()->assertSee('Manage tags')->getContent();
        // The picker gets the tag with its colour (inside @js(), so quotes are escaped).
        $this->assertMatchesRegularExpression('/Fornecedor.{1,20}color.{1,20}teal/', $html);
    }

    public function test_renaming_and_recolouring_a_tag(): void
    {
        $expense = $this->expense(['fixa']);
        $tag = Tag::query()->sole();

        $this->put(route('expenses.tags.update', $tag), ['name' => 'Custo fixo', 'color' => 'amber'])->assertSessionHas('success');

        $this->assertSame(['Custo fixo', 'custo-fixo', 'amber'], [$tag->fresh()->name, $tag->fresh()->slug, $tag->fresh()->color]);
        $this->assertSame(['custo-fixo'], $expense->fresh()->tags->pluck('slug')->all());
    }

    public function test_renaming_to_an_existing_tag_merges_them(): void
    {
        $a = $this->expense(['fixa']);
        $b = $this->expense(['mensal']);
        $both = $this->expense(['fixa', 'mensal']);

        $this->put(route('expenses.tags.update', Tag::query()->where('slug', 'mensal')->sole()), ['name' => 'Fixa'])
            ->assertSessionHas('success');

        $this->assertSame(['fixa'], Tag::query()->pluck('slug')->all());
        foreach ([$a, $b, $both] as $expense) {
            $this->assertSame(['fixa'], $expense->fresh()->tags->pluck('slug')->all());
        }
    }

    public function test_deleting_a_tag_keeps_its_expenses(): void
    {
        $expense = $this->expense(['temporária']);

        $this->delete(route('expenses.tags.destroy', Tag::query()->sole()))->assertSessionHas('success');

        $this->assertSame(0, Tag::query()->count());
        $this->assertTrue($expense->fresh()->exists);
        $this->assertCount(0, $expense->fresh()->tags);
    }

    public function test_the_tags_page_shows_usage_and_validates(): void
    {
        $this->expense(['fixa']);
        $this->expense(['fixa']);

        $this->get(route('expenses.tags.index'))
            ->assertOk()
            ->assertSee('#fixa')
            ->assertSee('2 expenses')
            ->assertSee('R$ 200,00');

        $this->post(route('expenses.tags.store'), ['name' => '###'])->assertSessionHasErrors('name');
        $this->post(route('expenses.tags.store'), ['name' => 'ok', 'color' => 'rainbow'])->assertSessionHasErrors('color');
    }
}
