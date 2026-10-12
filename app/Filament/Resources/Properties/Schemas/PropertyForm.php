<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên bất động sản')
                    ->required(),
                TextInput::make('address')
                    ->label('Địa chỉ')
                    ->required(),
                Textarea::make('description')
                    ->label('Mô tả')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->inline(false)
                    ->default(true),
            ]);
    }
}
