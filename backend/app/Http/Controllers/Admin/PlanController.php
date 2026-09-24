<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::withCount('subscriptions')->orderBy('price')->get();

        return view('admin.plans.index', ['plans' => $plans]);
    }

    public function create(): View
    {
        return view('admin.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $slug = $this->uniqueSlug($data['name']);
        $price = (int) $data['price'];

        $plan = new Plan($data);
        $plan->slug = $slug;
        $plan->price_version = 1;
        $plan->cashfree_plan_id = $price > 0 ? Plan::cashfreePlanId($slug, 1) : null;
        $plan->save();

        AdminAudit::record($request, 'plan.created', $plan, [
            'price' => $plan->price,
            'instances' => $plan->instances,
            'messages_per_month' => $plan->messages_per_month,
        ]);

        return redirect()->route('admin.plans.index')->with('status', "Plan \"{$plan->name}\" created.");
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', ['plan' => $plan]);
    }

    /**
     * Cashfree Plan objects are immutable once created (see CashfreeClient),
     * so a genuine price change gets a new cashfree_plan_id (price_version
     * bumped) rather than reusing the old one — existing subscribers keep
     * paying what they originally signed up for; only new subscriptions
     * pick up the new price. Every other field is a plain in-place update.
     */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validated($request);
        $newPrice = (int) $data['price'];
        $oldPrice = (int) $plan->getOriginal('price');

        $plan->fill($data);

        if ($newPrice !== $oldPrice) {
            $plan->price_version = $plan->price_version + 1;
            $plan->cashfree_plan_id = $newPrice > 0
                ? Plan::cashfreePlanId($plan->slug, $plan->price_version)
                : null;
        }

        // Only the fields the admin actually changed, as old → new
        // (not the internal price_version / cashfree_plan_id bookkeeping).
        $changes = [];
        foreach (array_keys($data) as $field) {
            if ($plan->isDirty($field)) {
                $changes[$field] = ['from' => $plan->getOriginal($field), 'to' => $plan->getAttribute($field)];
            }
        }

        $plan->save();

        if ($changes) {
            AdminAudit::record($request, 'plan.updated', $plan, $changes);
        }

        return redirect()->route('admin.plans.index')->with('status', "Plan \"{$plan->name}\" updated.");
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return redirect()->route('admin.plans.index')
                ->with('error', "Can't delete \"{$plan->name}\" — at least one user is still subscribed to it.");
        }

        AdminAudit::record($request, 'plan.deleted', $plan, ['price' => $plan->price]);

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', "Plan \"{$plan->name}\" deleted.");
    }

    /**
     * @return array{name: string, description: string, price: int, instances: int, messages_per_month: int, popular: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'price' => ['required', 'integer', 'min:0'],
            'instances' => ['required', 'integer', 'min:1'],
            'messages_per_month' => ['required', 'integer', 'min:1'],
            'popular' => ['sometimes', 'boolean'],
        ]);

        $data['popular'] = $request->boolean('popular');

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Plan::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
