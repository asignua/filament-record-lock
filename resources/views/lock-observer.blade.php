@php
    use Asignua\FilamentRecordLock\RecordLockPlugin;
    use Illuminate\Support\HtmlString;

    $plugin = RecordLockPlugin::resolve();
    $texts = __('filament-record-lock::filament-record-lock.modal');

    // Alpine fills them: the page re-renders only a partial when a modal action is mounted (see the comment below).
    $heading = new HtmlString('<span x-text="title"></span>');
    $description = new HtmlString('<span x-text="description"></span>');
@endphp

{{--
    Rendered at the start of every page that uses a lock trait, ALWAYS: Filament re-renders only the action-modals
    partial when a modal action is mounted, so a server-side @if on the lock state would never appear on a
    ManageRecords page. Everything that changes is therefore read from the page's public properties by Alpine.
--}}
<div
    wire:key="record-lock-observer"
    x-data="{
        url: @js($this->recordLockReleaseUrl()),
        token: @js(csrf_token()),
        texts: @js($texts),
        leave: null,
        init() {
            this.leave = () => this.release()
            window.addEventListener('pagehide', this.leave)
            document.addEventListener('livewire:navigating', this.leave)
        },
        destroy() {
            window.removeEventListener('pagehide', this.leave)
            document.removeEventListener('livewire:navigating', this.leave)
        },
        get reason() {
            return $wire.recordLockReason ?? 'locked'
        },
        get title() {
            return this.texts['heading_' + this.reason] ?? this.texts.heading_locked
        },
        get description() {
            const owner = $wire.recordLockOwner
            const key = this.reason === 'stale'
                ? 'stale'
                : this.reason + (owner ? '' : '_unknown')

            return (this.texts[key] ?? '')
                .replace(':user', owner ?? '')
                .replace(':since', $wire.recordLockSince ?? '')
        },
        // sendBeacon survives the page being torn down; a Livewire request would be cancelled with it.
        release() {
            const id = $wire.recordLockId
            const holder = $wire.recordLockHolder

            if (! id || ! holder) {
                return
            }

            const body = new FormData()
            body.append('_token', this.token)
            body.append('lock', id)
            body.append('holder', holder)

            navigator.sendBeacon(this.url, body)
        },
    }"
    x-effect="
        const blocked = $wire.recordLockBlocked
        $nextTick(() => $dispatch(blocked ? 'open-modal' : 'close-modal', { id: 'record-lock-notice' }))
    "
>
    @if ($plugin->shouldUsePollingToDetectPresence())
        {{-- Only while a record is locked by this page; a list page that merely CAN open a locked modal stays quiet. --}}
        <template x-if="$wire.recordLockActive">
            <div
                wire:poll{{ $plugin->shouldUsePollingKeepAlive() ? '.keep-alive' : '' }}{{ $plugin->shouldUsePollingVisible() ? '.visible' : '' }}.{{ $plugin->getPresencePollingInterval() }}s="recordLockHeartbeat"
            ></div>
        </template>
    @endif

    <x-filament::modal
        id="record-lock-notice"
        icon="heroicon-o-lock-closed"
        icon-color="warning"
        width="md"
        alignment="center"
        :close-button="false"
        :close-by-clicking-away="false"
        :close-by-escaping="false"
        :heading="$heading"
        :description="$description"
    >
        <x-slot name="footer">
            <div class="fi-ac fi-align-center" style="flex-wrap: wrap; justify-content: center; gap: 0.75rem">
                <x-filament::button
                    color="warning"
                    wire:click="recordLockReload"
                    x-show="reason === 'taken_over' || reason === 'stale'"
                >
                    {{ $texts['reload'] }}
                </x-filament::button>

                <x-filament::button
                    color="warning"
                    wire:click="recordLockTakeOver"
                    wire:loading.attr="disabled"
                    x-show="reason === 'locked' && $wire.recordLockCanUnlock"
                >
                    {{ $texts['unlock'] }}
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    tag="a"
                    x-show="$wire.recordLockViewUrl"
                    x-bind:href="$wire.recordLockViewUrl"
                >
                    {{ $texts['view'] }}
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    tag="a"
                    x-show="$wire.recordLockBackUrl"
                    x-bind:href="$wire.recordLockBackUrl"
                >
                    {{ $texts['back'] }}
                </x-filament::button>

                <x-filament::button color="gray" wire:click="recordLockDismiss" x-show="! $wire.recordLockBackUrl">
                    {{ $texts['back'] }}
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>
</div>
