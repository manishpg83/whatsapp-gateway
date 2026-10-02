<?php

namespace App\Http\Controllers;

use App\Models\BulkTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Bulk messages → Saved messages: reusable message texts (may contain
 * {name}) to load into a new campaign. Always scoped to the logged-in
 * user — another user's template id is a 404 (CLAUDE.md §5).
 */
class BulkTemplateController extends Controller
{
    public function index(Request $request): View
    {
        return view('bulk.templates.index', [
            'templates' => $request->user()->bulkTemplates()->orderBy('name')->get(),
            'max' => BulkTemplate::MAX_PER_USER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->user()->bulkTemplates()->count() >= BulkTemplate::MAX_PER_USER) {
            throw ValidationException::withMessages(['name' => 'You already have '.BulkTemplate::MAX_PER_USER.' saved messages. Delete one first.']);
        }

        $request->user()->bulkTemplates()->create($data);

        return redirect()->route('bulk.templates.index')->with('status', "Saved \"{$data['name']}\".");
    }

    public function edit(Request $request, int $template): View
    {
        return view('bulk.templates.edit', ['template' => $this->findOwned($request, $template)]);
    }

    public function update(Request $request, int $template): RedirectResponse
    {
        $model = $this->findOwned($request, $template);
        $model->update($this->validated($request));

        return redirect()->route('bulk.templates.index')->with('status', "Updated \"{$model->name}\".");
    }

    public function destroy(Request $request, int $template): RedirectResponse
    {
        $model = $this->findOwned($request, $template);
        $model->delete();

        return redirect()->route('bulk.templates.index')->with('status', "Deleted \"{$model->name}\".");
    }

    /**
     * @return array{name: string, body: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:4096'],
        ], [
            'body.required' => 'Write the message to save.',
        ]);
    }

    private function findOwned(Request $request, int $id): BulkTemplate
    {
        return $request->user()->bulkTemplates()->whereKey($id)->firstOrFail();
    }
}
