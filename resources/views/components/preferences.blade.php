{{-- Language (Google Translate) + light/dark theme controls, used in the app header and on auth pages. --}}
<div class="flex items-center gap-2 notranslate" translate="no">
    <label x-data="languageSwitcher" class="relative flex items-center">
        <span class="sr-only">Language</span>
        <x-svg-icon name="language" class="pointer-events-none absolute left-2 size-4 text-slate-500" />
        <select class="input w-auto py-1.5 pr-8 pl-7 text-xs" :value="current" @change="change($event.target.value)">
            <template x-for="[code, name] in languages" :key="code">
                <option :value="code" x-text="name" :selected="code === current"></option>
            </template>
        </select>
    </label>

    <div x-data="themeSwitcher" class="flex rounded-lg border border-slate-200 bg-surface p-0.5" role="radiogroup" aria-label="Theme">
        <template x-for="option in options" :key="option.value">
            <button type="button" role="radio" :aria-checked="theme === option.value" :title="option.label" @click="set(option.value)"
                    :class="theme === option.value ? 'bg-slate-100 text-slate-900' : 'text-slate-400 hover:text-slate-700'"
                    class="rounded-md p-1.5">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path x-show="option.icon === 'sun'" stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    <path x-show="option.icon === 'moon'" stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    <path x-show="option.icon === 'computer'" stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                </svg>
            </button>
        </template>
    </div>
</div>
