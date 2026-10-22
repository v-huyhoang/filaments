<?php

namespace App\Filament\Resources\MeterReadings\Pages;

use App\Actions\MeterReadings\RecordPropertyMeterReadingsAction;
use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Models\MeterReading;
use App\Services\PropertyMeterReadingInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class RecordPropertyMeterReadings extends Page
{
    protected static string $resource = MeterReadingResource::class;

    protected string $view = 'filament.meter-readings.record-property';

    /** @var array{property_id?: int|null, billing_period?: string|null} */
    public ?array $data = [];

    #[Locked]
    public ?int $loadedPropertyId = null;

    #[Locked]
    public ?string $loadedPeriod = null;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $rooms = [];

    /** @var array<int, string> */
    #[Locked]
    public array $services = [];

    /** @var array<string, array{previous_value: ?string, current_value: ?string}> */
    public array $readings = [];

    public function mount(): void
    {
        Gate::authorize('create', MeterReading::class);
        $this->form->fill(['billing_period' => today()->format('Y-m')]);
    }

    public function getTitle(): string
    {
        return __('meter_readings.bulk.title');
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('history')->label(__('meter_readings.bulk.history'))->url(MeterReadingResource::getUrl())];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(2)->components([
            Select::make('property_id')->label(__('meter_readings.fields.property'))
                ->options(fn (): array => app(PropertyMeterReadingInput::class)->properties(auth()->user()))
                ->searchable()->required()->live()->afterStateUpdated(fn () => $this->clearReadings()),
            TextInput::make('billing_period')->label(__('meter_readings.fields.billing_period'))
                ->type('month')->rule('date_format:Y-m')->required()->live()
                ->afterStateUpdated(fn () => $this->clearReadings()),
        ]);
    }

    public function clearReadings(): void
    {
        $this->loadedPropertyId = null;
        $this->loadedPeriod = null;
        $this->rooms = [];
        $this->services = [];
        $this->readings = [];
        $this->resetValidation();
    }

    public function loadReadings(): void
    {
        $data = $this->form->getState();
        $result = app(PropertyMeterReadingInput::class)->load(auth()->user(), (int) $data['property_id'], $data['billing_period']);
        $this->loadedPropertyId = (int) $data['property_id'];
        $this->loadedPeriod = $data['billing_period'];
        $this->rooms = $result['rooms'];
        $this->services = $result['services'];
        $this->readings = $result['readings'];
        $this->resetValidation();
    }

    public function save(): void
    {
        Gate::authorize('create', MeterReading::class);
        $data = $this->form->getState();
        if ($this->loadedPropertyId !== (int) $data['property_id'] || $this->loadedPeriod !== $data['billing_period']) {
            throw ValidationException::withMessages(['readings' => __('meter_readings.bulk.reload')]);
        }

        $readings = [];
        foreach ($this->rooms as $roomId => $room) {
            foreach ($room['meters'] as $serviceId => $meter) {
                if (! $meter['recorded']) {
                    $values = $this->readings[$meter['key']] ?? [];
                    $readings[$meter['key']] = [
                        'room_id' => $roomId, 'service_id' => $serviceId,
                        'previous_value' => $values['previous_value'] ?? null,
                        'current_value' => $values['current_value'] ?? null,
                    ];
                }
            }
        }
        $count = app(RecordPropertyMeterReadingsAction::class)->handle(auth()->user(), [
            'property_id' => $this->loadedPropertyId, 'billing_period' => $this->loadedPeriod, 'readings' => $readings,
        ]);

        $this->loadReadings();
        Notification::make()->title(__('meter_readings.bulk.saved', ['count' => $count]))->success()->send();
    }
}
