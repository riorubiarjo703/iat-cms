<?php

namespace App\Filament\Resources\CodeSnippets\Pages;

use App\Enums\SnippetPosition;
use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use App\Models\CodeSnippet;
use App\Support\SnippetTemplates;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCodeSnippets extends ListRecords
{
    protected static string $resource = CodeSnippetResource::class;

    public function getHeading(): string
    {
        return 'Code Snippets';
    }

    public function getSubheading(): ?string
    {
        return 'Inject scripts, styles, and meta tags into your pages';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('help')
                ->label('Help')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('About code snippets')
                ->modalDescription(SnippetPosition::helperText().' Within a position, lower priority numbers load first.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            static::templateAction(),

            CreateAction::make()->label('Add Snippet'),
        ];
    }

    /**
     * The "Use Template" action, shared by the header and the table's empty
     * state. `ListRecords` implements `Tables\Contracts\HasTable`, so the
     * table is the same Livewire component as the page — an empty-state
     * action's modal reaches `applyTemplate()` exactly as the header one
     * does, which is why this is one action definition rather than two
     * copies of the template grid markup.
     */
    public static function templateAction(): Action
    {
        return Action::make('template')
            ->label('Template')
            ->icon('heroicon-o-squares-2x2')
            ->color('gray')
            ->modalHeading('Use Template')
            ->modalDescription('Choose a template to quickly add tracking codes or custom snippets.')
            ->modalContent(view('filament.modals.snippet-templates', [
                'templates' => SnippetTemplates::all(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /**
     * Creates a template's snippets, switched off, and opens the first for editing.
     *
     * Off, because every tracking template carries a placeholder id — activating
     * on creation would put a broken tag on every page of the live site before the
     * operator had a chance to type their own. It also handles Google Tag Manager,
     * which needs two records in two positions and so does not fit pre-filling a
     * single create form.
     */
    public function applyTemplate(string $key): void
    {
        // Every panel account is a full administrator today, so this changes
        // nothing yet — but it is the one write path this feature adds that a
        // future resource policy would not automatically cover once Roles and
        // Permissions exist, so the gate is here ahead of that landing.
        abort_unless(static::getResource()::canCreate(), 403);

        $template = SnippetTemplates::find($key);

        if ($template === null) {
            return;
        }

        $created = collect($template['snippets'])->map(fn (array $attributes) => CodeSnippet::create([
            ...$attributes,
            'is_active' => false,
            'skip_for_admins' => true,
            'description' => 'Created from the '.$template['label'].' template. Replace the placeholder id, then switch it on.',
        ]));

        Notification::make()
            ->title(count($created) === 1
                ? $created->first()->name.' created, switched off'
                : count($created).' snippets created, switched off')
            ->body('Replace the placeholder id, then enable it.')
            ->success()
            ->send();

        $this->redirect(CodeSnippetResource::getUrl('edit', ['record' => $created->first()]));
    }
}
