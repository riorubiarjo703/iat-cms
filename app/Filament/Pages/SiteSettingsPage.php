<?php

namespace App\Filament\Pages;

use App\Concerns\EditsSingletonRecord;
use App\Filament\Pages\Concerns\ChecksPagePermission;
use App\Filament\Support\LocaleTabs;
use App\Filament\Support\MediaField;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class SiteSettingsPage extends Page
{
    use EditsSingletonRecord;
    use ChecksPagePermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Site Settings';

    protected static ?string $slug = 'site-settings';

    protected string $view = 'filament.pages.site-settings-page';

    public static function permission(): string
    {
        return 'settings.manage';
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected function singletonRecord(): Model
    {
        return SiteSetting::singleton();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('site_name')->label('Site name')->required()->maxLength(255),
                        Select::make('default_locale')
                            ->label('Default language')
                            ->options(SiteSetting::LOCALES)
                            ->default('en')
                            ->required()
                            // Options alone only constrain the UI. Without a
                            // server-side rule, fillForm()/an API call can
                            // still persist an arbitrary string, which would
                            // blank all six `{!! !!}` headings on the public
                            // homepage via the `?? ''` fallback.
                            ->in(array_keys(SiteSetting::LOCALES)),
                        MediaField::image('logo', 'Logo', 'branding'),
                        MediaField::image('favicon', 'Favicon', 'branding'),
                    ])
                    ->columns(2),

                LocaleTabs::make(fn (string $locale) => [
                    TextInput::make("meta_title.$locale")
                        ->label('Meta title')
                        ->required(LocaleTabs::isFallback($locale))
                        ->maxLength(255),
                    Textarea::make("meta_description.$locale")
                        ->label('Meta description')
                        ->rows(3)
                        ->required(LocaleTabs::isFallback($locale))
                        ->maxLength(500),
                ], 'Search & Social Preview'),

                // Organisation facts, not homepage copy: the footer shows
                // these on every page, so they cannot live on HomepageContent.
                Section::make('Contact')
                    ->description('Shown in the site footer and the contact section.')
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Phone')
                            ->maxLength(255),
                        Textarea::make('contact_address')
                            ->label('Address')
                            ->helperText('One line per row — the footer renders each on its own line.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Social Links')
                    ->schema([
                        TextInput::make('social.instagram')->label('Instagram')->url()->maxLength(255),
                        TextInput::make('social.linkedin')->label('LinkedIn')->url()->maxLength(255),
                        TextInput::make('social.youtube')->label('YouTube')->url()->maxLength(255),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save'),
        ];
    }
}
