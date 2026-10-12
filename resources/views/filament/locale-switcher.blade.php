<x-filament::dropdown.list>
    <form
        method="POST"
        action="{{ route('locale.switch', ['locale' => 'en']) }}"
        role="group"
        aria-label="{{ __('locale.switcher') }}"
        class="fi-theme-switcher"
    >
        @csrf

        @foreach (['en', 'vi'] as $locale)
            <button
                type="submit"
                formaction="{{ route('locale.switch', ['locale' => $locale]) }}"
                aria-label="{{ __('locale.languages.'.$locale) }}"
                aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}"
                title="{{ __('locale.languages.'.$locale) }}"
                @class(['fi-theme-switcher-btn', 'fi-active' => app()->getLocale() === $locale])
            >
                {{ strtoupper($locale) }}
            </button>
        @endforeach
    </form>
</x-filament::dropdown.list>
