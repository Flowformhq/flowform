<x-filament-panels::page>
    <div class="space-y-8">
        <section class="space-y-2">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Webhook Subscriptions
            </h2>
            {{ $this->table }}
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Recent Webhook Deliveries
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Read-only delivery log across your forms. Open a form to filter by status or retry failed deliveries.
                </p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <table class="w-full divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Time</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Form</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Target Host</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Status</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Code</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Attempt</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Duration</th>
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">Response Body</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($this->recentDeliveries as $delivery)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $delivery->attempted_at?->toDateTimeString() ?? '—' }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ $delivery->form?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $delivery->targetHost() }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $delivery->status === 'succeeded' ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' : ($delivery->status === 'failed' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400') }}">
                                        {{ $delivery->status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $delivery->response_code ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $delivery->attempt }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $delivery->duration_ms !== null ? $delivery->duration_ms.' ms' : '—' }}
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">
                                    {{ \Illuminate\Support\Str::limit((string) ($delivery->response_body ?? '—'), 80) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No webhook deliveries recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
