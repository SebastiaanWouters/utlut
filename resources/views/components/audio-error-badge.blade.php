@props(['errorCode' => null, 'message' => null, 'nextRetryAt' => null, 'retryCount' => 0])

@php
use App\Enums\AudioErrorCode;
$userMessage = $errorCode ? AudioErrorCode::tryFrom($errorCode)?->userMessage() : ($message ?? 'Failed');
$countdownSeconds = $nextRetryAt ? max(0, now()->diffInSeconds($nextRetryAt, false)) : null;
@endphp

<span
    x-data="{ countdown: {{ $countdownSeconds ?? 'null' }} }"
    x-init="if (countdown !== null && countdown > 0) { setInterval(() => countdown = Math.max(0, countdown - 1), 1000) }"
    class="shrink-0 rounded-md bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-600 dark:bg-red-950/50 dark:text-red-400"
    title="{{ $userMessage }}"
>
    <template x-if="countdown !== null && countdown > 0">
        <span x-text="'Retry in ' + countdown + 's'"></span>
    </template>
    <template x-if="countdown === null || countdown <= 0">
        <span>{{ __('Audio failed') }}</span>
    </template>
</span>
