<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    public function test_locale_switcher_is_rendered_in_admin_user_menu(): void
    {
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->make());

        $this->withSession(['locale' => 'vi'])->get('/admin')
            ->assertOk()
            ->assertSee('aria-label="Ngôn ngữ"', false)
            ->assertSee('formaction="'.route('locale.switch', ['locale' => 'en']).'"', false)
            ->assertSee('formaction="'.route('locale.switch', ['locale' => 'vi']).'"', false);
    }

    public function test_sidebar_resource_labels_follow_selected_locale(): void
    {
        $resources = [
            PropertyResource::class => ['Properties', 'Bất Động Sản'],
            RoomResource::class => ['Rooms', 'Phòng'],
            TenantResource::class => ['Tenants', 'Người Thuê'],
            ContractResource::class => ['Contracts', 'Hợp Đồng'],
            RoomReservationResource::class => ['Room Reservations', 'Giữ Chỗ'],
        ];

        $this->withSession(['locale' => 'en'])->get('/admin/login')->assertOk();

        foreach ($resources as $resource => $labels) {
            $this->assertSame($labels[0], $resource::getNavigationLabel());
        }

        $this->withSession(['locale' => 'vi'])->get('/admin/login')->assertOk();

        foreach ($resources as $resource => $labels) {
            $this->assertSame($labels[1], $resource::getNavigationLabel());
        }
    }

    public function test_locale_switcher_labels_and_selected_button_follow_current_locale(): void
    {
        $this->actingAs(User::factory()->make());

        $items = Filament::getPanel('admin')->getUserMenuItems();

        app()->setLocale('vi');

        $this->view('filament.locale-switcher')
            ->assertSee('aria-label="Ngôn ngữ"', false)
            ->assertSee('aria-label="Tiếng Anh"', false)
            ->assertSee('aria-label="Tiếng Việt"', false)
            ->assertSeeInOrder(['aria-label="Tiếng Anh"', 'aria-pressed="false"', 'aria-label="Tiếng Việt"', 'aria-pressed="true"'], false);

        app()->setLocale('en');

        $this->view('filament.locale-switcher')
            ->assertSee('aria-label="Language"', false)
            ->assertSeeInOrder(['aria-label="English"', 'aria-pressed="true"', 'aria-label="Vietnamese"', 'aria-pressed="false"'], false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_token"', false)
            ->assertSee(route('locale.switch', ['locale' => 'en']), false)
            ->assertSee(route('locale.switch', ['locale' => 'vi']), false);

        $this->assertArrayNotHasKey('locale-en', $items);
        $this->assertArrayNotHasKey('locale-vi', $items);
    }

    public function test_switching_to_vietnamese_persists_language(): void
    {
        $this->post(route('locale.switch', ['locale' => 'vi']))
            ->assertRedirect(route('filament.admin.pages.dashboard'))
            ->assertSessionHas('locale', 'vi');

        $this->get('/admin/login')->assertSee('lang="vi"', false);
    }

    public function test_switching_back_to_english_persists_language(): void
    {
        $this->withSession(['locale' => 'vi'])
            ->post(route('locale.switch', ['locale' => 'en']))
            ->assertRedirect(route('filament.admin.pages.dashboard'))
            ->assertSessionHas('locale', 'en');

        $this->get('/admin/login')->assertSee('lang="en"', false);
    }

    public function test_unsupported_language_does_not_change_selection(): void
    {
        $this->withSession(['locale' => 'vi'])
            ->post(route('locale.switch', ['locale' => 'fr']))
            ->assertNotFound()
            ->assertSessionHas('locale', 'vi');
    }

    public function test_language_cannot_be_changed_by_get_request(): void
    {
        $this->get(route('locale.switch', ['locale' => 'vi']))
            ->assertStatus(405)
            ->assertSessionMissing('locale');
    }
}
