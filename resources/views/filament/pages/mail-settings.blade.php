<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Configuration Card -->
        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Transactional Mail Transport</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                Configure how FlowForm forwards submission alerts and transactional emails. If left unconfigured, the engine falls back to server <code>.env</code> settings.
            </p>

            <form wire:submit.prevent="save" class="space-y-5">
                <!-- Driver Selector -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-2">
                        Mail Provider / Protocol
                    </label>
                    <select wire:model.live="driver" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-cyan-500 focus:border-cyan-500">
                        <option value="smtp">Custom SMTP Relay (Hostgator, cPanel, Mailtrap, Custom)</option>
                        <option value="ses">Amazon SES (Simple Email Service)</option>
                        <option value="resend">Resend (Modern Developer API)</option>
                        <option value="postmark">Postmark (High Deliverability)</option>
                        <option value="log">Local Log (Development / Testing)</option>
                    </select>
                </div>

                @if($driver === 'smtp')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">SMTP Host</label>
                            <input type="text" wire:model="host" placeholder="smtp.mailgun.org or mail.yourdomain.com" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Port</label>
                            <input type="number" wire:model="port" placeholder="587" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Encryption</label>
                            <select wire:model="encryption" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white">
                                <option value="tls">TLS (Recommended)</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                            <input type="text" wire:model="username" placeholder="smtp_user" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                            <input type="password" wire:model="password" placeholder="••••••••••••" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                        </div>
                    </div>
                @endif

                @if($driver === 'ses')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">AWS Access Key ID</label>
                            <input type="text" wire:model="username" placeholder="AKIA..." class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">AWS Secret Access Key</label>
                            <input type="password" wire:model="password" placeholder="••••••••••••" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">AWS Region</label>
                            <input type="text" wire:model="region" placeholder="us-east-1" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                        </div>
                    </div>
                @endif

                @if($driver === 'resend' || $driver === 'postmark')
                    <div class="pt-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $driver === 'resend' ? 'Resend API Key (re_...)' : 'Postmark Server API Token' }}
                        </label>
                        <input type="password" wire:model="api_key" placeholder="••••••••••••" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                    </div>
                @endif

                <!-- Sender Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Default From Email</label>
                        <input type="email" wire:model="from_address" placeholder="noreply@yourdomain.com" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Default From Name</label>
                        <input type="text" wire:model="from_name" placeholder="FlowForm Intake" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3">
                    <input type="checkbox" id="is_active" wire:model="is_active" class="rounded border-gray-300 text-cyan-500 focus:ring-cyan-500" />
                    <label for="is_active" class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        Enable this mail provider configuration for outbound form notifications
                    </label>
                </div>

                <div class="pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-semibold shadow-xs transition">
                        Save Mail Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Diagnostic Testing Card -->
        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Diagnostic Connectivity Test</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                Verify that your SMTP server or API credentials can successfully connect and dispatch an email before relying on it in production.
            </p>

            <div class="flex flex-col sm:flex-row items-center gap-3 max-w-xl">
                <input type="email" wire:model="test_recipient" placeholder="your-email@example.com" class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white" />
                <button type="button" wire:click="sendTestEmail" wire:loading.attr="disabled" class="w-full sm:w-auto shrink-0 px-5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-xs font-semibold transition">
                    <span wire:loading.remove wire:target="sendTestEmail">Send Diagnostic Test</span>
                    <span wire:loading wire:target="sendTestEmail">Connecting...</span>
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
