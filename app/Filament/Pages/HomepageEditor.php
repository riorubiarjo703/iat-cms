<?php

namespace App\Filament\Pages;

use App\Concerns\EditsSingletonRecord;
use App\Filament\Support\LocaleTabs;
use App\Models\HomepageContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class HomepageEditor extends Page
{
    use EditsSingletonRecord;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $title = 'Homepage';

    protected static ?string $slug = 'homepage';

    /** Non-static in Filament 5. */
    protected string $view = 'filament.pages.homepage-editor';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected function singletonRecord(): Model
    {
        return HomepageContent::singleton();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Homepage')
                    ->tabs([
                        ...$this->localeTabs(),
                        Tab::make('Media & Links')->schema($this->mediaFields()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * One tab per language, each holding every translatable field for that
     * language grouped by page section.
     *
     * @return array<int, Tab>
     */
    private function localeTabs(): array
    {
        return LocaleTabs::make(fn (string $locale) => [
            Section::make('Brand & Navigation')->schema([
                TextInput::make("brand_sub.$locale")
                    ->label('Brand subtitle'),
            ]),
            Section::make('Hero')->schema([
                Textarea::make("hero_line.$locale")
                    ->label('Hero headline')
                    ->rows(3)
                    ->helperText('Each new line becomes one animated line of the headline.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("hero_sub.$locale")
                    ->label('Hero paragraph')
                    ->rows(3)
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('About')->schema([
                Textarea::make("about_heading.$locale")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                Textarea::make("about_body.$locale")->label('Body')->rows(4)->required(LocaleTabs::isFallback($locale)),
                TextInput::make("about_cta_label.$locale")->label('Button label')->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('District')->schema([
                Textarea::make("district_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("district_body.$locale")->label('Body')->rows(3)->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('Facilities')->schema([
                Textarea::make("facilities_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("facilities_body.$locale")->label('Body')->rows(3)->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('News')->schema([
                Textarea::make("news_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
                TextInput::make("news_cta_label.$locale")->label('Button label')->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('Contact')->schema([
                Textarea::make("contact_heading.$locale")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Each new line becomes one animated line.')
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
            Section::make('Marquee')->schema([
                TextInput::make("marquee_text.$locale")
                    ->label('Scrolling strip text')
                    ->helperText('Repeated automatically to fill the strip.')
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
        ])->getDefaultChildComponents();
    }

    /**
     * @return array<int, mixed>
     */
    private function mediaFields(): array
    {
        return [
            Section::make('Images')->schema([
                FileUpload::make('hero_image')
                    ->label('Hero image')
                    ->image()
                    ->disk('public')
                    ->directory('uploads/homepage')
                    ->visibility('public')
                    ->maxSize(5120),
                FileUpload::make('about_image')
                    ->label('About image')
                    ->image()
                    ->disk('public')
                    ->directory('uploads/homepage')
                    ->visibility('public')
                    ->maxSize(5120),
            ]),
            Section::make('Links & Contact')->schema([
                TextInput::make('about_cta_url')->label('About button URL')->maxLength(255),
                TextInput::make('contact_email')->label('Email')->email()->maxLength(255),
                TextInput::make('contact_phone')->label('Phone')->maxLength(255),
                Textarea::make('contact_address')
                    ->label('Address')
                    ->rows(3)
                    ->helperText('Each new line becomes one line in the district location panel.'),
            ]),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save'),
            Action::make('view')->label('View homepage')->url('/')->openUrlInNewTab()->color('gray'),
        ];
    }
}
