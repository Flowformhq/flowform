<x-filament-panels::page>
    @php
        $isDemo = str_contains(request()->getHost(), 'demo') || request()->has('demo');
    @endphp

    <div class="space-y-6 max-w-5xl">
        @if($isDemo)
            <div class="rounded-xl border border-cyan-500/30 bg-cyan-950/20 p-4 text-xs text-cyan-200 shadow-sm backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500"></span>
                    </span>
                    <div class="flex-1">
                        <strong class="font-semibold text-cyan-300">Live Demo Sandbox:</strong>
                        <span class="text-cyan-100/90 ml-1">You are exploring in demo mode. You can test provider switches and diagnostic probes without altering production mail servers.</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Main Configuration Card -->
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-6 shadow-xl shadow-black/40">
            <!-- Header with Active Badge -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-800/80">
                <div>
                    <h2 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        <span>Transactional Mail Transport</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium {{ $this->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400' }}">
                            {{ $this->is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Configure outbound SMTP relays or direct APIs for submission notifications, auto-responders, and engine alerts.
                    </p>
                </div>

                <div class="flex items-center gap-2 text-xs font-mono text-slate-400 bg-slate-900/60 px-3 py-1.5 rounded-lg border border-slate-800 self-start sm:self-auto">
                    <span class="text-slate-500">Current:</span>
                    <span class="text-cyan-400 font-semibold uppercase">{{ $this->driver }}</span>
                </div>
            </div>

            <form wire:submit.prevent="save" class="space-y-6 pt-6">
                <!-- Provider Selector Grid -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-3">
                        Select Mail Protocol / Provider
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- SMTP -->
                        <button type="button"
                                wire:click="selectDriver('smtp')"
                                class="flex flex-col items-start p-3.5 rounded-xl border text-left transition-all duration-150 {{ $this->driver === 'smtp' ? 'border-cyan-500 bg-cyan-950/20 shadow-sm shadow-cyan-500/10 ring-1 ring-cyan-500/50' : 'border-slate-800 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700' }}">
                            <div class="flex items-center justify-between w-full mb-2">
                                <svg class="w-5 h-5 {{ $this->driver === 'smtp' ? 'text-cyan-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                </svg>
                                <span class="h-2 w-2 rounded-full {{ $this->driver === 'smtp' ? 'bg-cyan-400' : 'bg-transparent' }}"></span>
                            </div>
                            <span class="text-xs font-semibold text-white">Custom SMTP</span>
                            <span class="text-[11px] text-slate-400 mt-0.5">Hostinger, cPanel, Mailtrap</span>
                        </button>

                        <!-- Amazon SES -->
                        <button type="button"
                                wire:click="selectDriver('ses')"
                                class="flex flex-col items-start p-3.5 rounded-xl border text-left transition-all duration-150 {{ $this->driver === 'ses' ? 'border-cyan-500 bg-cyan-950/20 shadow-sm shadow-cyan-500/10 ring-1 ring-cyan-500/50' : 'border-slate-800 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700' }}">
                            <div class="flex items-center justify-between w-full mb-2">
                                <svg class="w-5 h-5 {{ $this->driver === 'ses' ? 'text-cyan-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"/>
                                </svg>
                                <span class="h-2 w-2 rounded-full {{ $this->driver === 'ses' ? 'bg-cyan-400' : 'bg-transparent' }}"></span>
                            </div>
                            <span class="text-xs font-semibold text-white">Amazon SES</span>
                            <span class="text-[11px] text-slate-400 mt-0.5">High volume, AWS cloud</span>
                        </button>

                        <!-- Resend -->
                        <button type="button"
                                wire:click="selectDriver('resend')"
                                class="flex flex-col items-start p-3.5 rounded-xl border text-left transition-all duration-150 {{ $this->driver === 'resend' ? 'border-cyan-500 bg-cyan-950/20 shadow-sm shadow-cyan-500/10 ring-1 ring-cyan-500/50' : 'border-slate-800 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700' }}">
                            <div class="flex items-center justify-between w-full mb-2">
                                <svg class="w-5 h-5 {{ $this->driver === 'resend' ? 'text-cyan-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span class="h-2 w-2 rounded-full {{ $this->driver === 'resend' ? 'bg-cyan-400' : 'bg-transparent' }}"></span>
                            </div>
                            <span class="text-xs font-semibold text-white">Resend</span>
                            <span class="text-[11px] text-slate-400 mt-0.5">Developer REST API</span>
                        </button>

                        <!-- Postmark -->
                        <button type="button"
                                wire:click="selectDriver('postmark')"
                                class="flex flex-col items-start p-3.5 rounded-xl border text-left transition-all duration-150 {{ $this->driver === 'postmark' ? 'border-cyan-500 bg-cyan-950/20 shadow-sm shadow-cyan-500/10 ring-1 ring-cyan-500/50' : 'border-slate-800 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700' }}">
                            <div class="flex items-center justify-between w-full mb-2">
                                <svg class="w-5 h-5 {{ $this->driver === 'postmark' ? 'text-cyan-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="h-2 w-2 rounded-full {{ $this->driver === 'postmark' ? 'bg-cyan-400' : 'bg-transparent' }}"></span>
                            </div>
                            <span class="text-xs font-semibold text-white">Postmark</span>
                            <span class="text-[11px] text-slate-400 mt-0.5">Strict inbox deliverability</span>
                        </button>

                        <!-- Local Log -->
                        <button type="button"
                                wire:click="selectDriver('log')"
                                class="flex flex-col items-start p-3.5 rounded-xl border text-left transition-all duration-150 {{ $this->driver === 'log' ? 'border-cyan-500 bg-cyan-950/20 shadow-sm shadow-cyan-500/10 ring-1 ring-cyan-500/50' : 'border-slate-800 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700' }}">
                            <div class="flex items-center justify-between w-full mb-2">
                                <svg class="w-5 h-5 {{ $this->driver === 'log' ? 'text-cyan-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="h-2 w-2 rounded-full {{ $this->driver === 'log' ? 'bg-cyan-400' : 'bg-transparent' }}"></span>
                            </div>
                            <span class="text-xs font-semibold text-white">Local Log</span>
                            <span class="text-[11px] text-slate-400 mt-0.5">Offline mock testing</span>
                        </button>
                    </div>
                </div>

                <!-- Provider Fields -->
                <div class="rounded-xl border border-slate-800/80 bg-slate-950/50 p-5 space-y-4">
                    @if($this->driver === 'smtp')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">SMTP Host</label>
                                <div class="relative">
                                    <input type="text"
                                           wire:model="host"
                                           placeholder="smtp.mailgun.org or mail.yourdomain.com"
                                           class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">Port</label>
                                <input type="number"
                                       wire:model="port"
                                       placeholder="587"
                                       class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">Encryption Protocol</label>
                                <select wire:model="encryption"
                                        class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20">
                                    <option value="tls">TLS (STARTTLS — Port 587)</option>
                                    <option value="ssl">SSL (SMTPS — Port 465)</option>
                                    <option value="">None (Insecure / Port 25)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">SMTP Username</label>
                                <input type="text"
                                       wire:model="username"
                                       placeholder="postmaster@yourdomain.com"
                                       class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">SMTP Password</label>
                                <div class="relative">
                                    <input type="{{ $this->showPassword ? 'text' : 'password' }}"
                                           wire:model="password"
                                           placeholder="••••••••••••"
                                           class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 pr-10 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20" />
                                    <button type="button"
                                            wire:click="toggleShowPassword"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-cyan-400 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($this->showPassword)
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            @endif
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($this->driver === 'ses')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">AWS Access Key ID</label>
                                <input type="text"
                                       wire:model="username"
                                       placeholder="AKIAIOSFODNN7EXAMPLE"
                                       class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 font-mono text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">AWS Secret Access Key</label>
                                <div class="relative">
                                    <input type="{{ $this->showPassword ? 'text' : 'password' }}"
                                           wire:model="password"
                                           placeholder="••••••••••••"
                                           class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 pr-10 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 font-mono text-xs" />
                                    <button type="button"
                                            wire:click="toggleShowPassword"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-cyan-400 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($this->showPassword)
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            @endif
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">AWS Region</label>
                                <select wire:model="region"
                                        class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 font-mono text-xs">
                                    <option value="us-east-1">US East (N. Virginia) — us-east-1</option>
                                    <option value="us-east-2">US East (Ohio) — us-east-2</option>
                                    <option value="us-west-2">US West (Oregon) — us-west-2</option>
                                    <option value="eu-west-1">EU (Ireland) — eu-west-1</option>
                                    <option value="eu-central-1">EU (Frankfurt) — eu-central-1</option>
                                    <option value="ap-southeast-1">Asia Pacific (Singapore) — ap-southeast-1</option>
                                </select>
                            </div>
                        </div>
                    @endif

                    @if($this->driver === 'resend' || $this->driver === 'postmark')
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">
                                {{ $this->driver === 'resend' ? 'Resend API Key' : 'Postmark Server API Token' }}
                            </label>
                            <div class="relative">
                                <input type="{{ $this->showApiKey ? 'text' : 'password' }}"
                                       wire:model="api_key"
                                       placeholder="{{ $this->driver === 'resend' ? 're_123456789...' : 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' }}"
                                       class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 pr-10 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 font-mono text-xs" />
                                <button type="button"
                                        wire:click="toggleShowApiKey"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-cyan-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @if($this->showApiKey)
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        @endif
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($this->driver === 'log')
                        <div class="flex items-center gap-3 text-xs text-slate-400 p-2">
                            <svg class="w-5 h-5 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Local log driver records outbound emails directly to <code>storage/logs/laravel.log</code> without attempting network handshakes. Ideal for offline staging.</span>
                        </div>
                    @endif
                </div>

                <!-- Sender Identity Section -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-300 mb-3">
                        Outbound Sender Identity
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Default "From" Email</label>
                            <input type="email"
                                   wire:model="from_address"
                                   placeholder="noreply@flowformhq.com"
                                   class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20" />
                            <p class="text-[11px] text-slate-400 mt-1">Must match verified sender domains on SES, Resend, or Postmark.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Default "From" Display Name</label>
                            <input type="text"
                                   wire:model="from_name"
                                   placeholder="FlowForm Intake"
                                   class="w-full rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20" />
                            <p class="text-[11px] text-slate-400 mt-1">Brand name recipient sees in their email inbox.</p>
                        </div>
                    </div>
                </div>

                <!-- Activation & Submit Bar -->
                <div class="pt-4 border-t border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <label class="relative flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox"
                               wire:model="is_active"
                               class="sr-only peer" />
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                        <span class="text-xs font-medium text-slate-300">
                            Enable this configuration for outbound form notifications
                        </span>
                    </label>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold text-xs tracking-wide shadow-md shadow-cyan-500/20 hover:shadow-cyan-500/30 transition-all duration-150">
                        <span wire:loading.remove wire:target="save" class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Mail Settings
                        </span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Diagnostic Testing Console Card -->
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-6 shadow-xl shadow-black/40">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800/80 mb-5">
                <div>
                    <h2 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>Diagnostic Connectivity Probe</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Dispatch a synthetic probe to test network handshakes, authentication, and inbox delivery before going live.
                    </p>
                </div>
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-mono bg-slate-900 border border-slate-800 text-slate-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                    Ready
                </span>
            </div>

            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row items-stretch gap-3">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="email"
                               wire:model="test_recipient"
                               placeholder="your-email@company.com"
                               class="w-full pl-9 rounded-lg border border-slate-700 bg-slate-900/80 text-sm text-slate-100 placeholder-slate-500 px-3 py-2.5 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20" />
                    </div>

                    <button type="button"
                            wire:click="sendTestEmail"
                            wire:loading.attr="disabled"
                            class="sm:w-auto shrink-0 inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-750 text-slate-200 hover:text-white font-medium text-xs shadow-xs hover:border-slate-600 transition">
                        <span wire:loading.remove wire:target="sendTestEmail" class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Dispatch Diagnostic Probe
                        </span>
                        <span wire:loading wire:target="sendTestEmail" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin text-cyan-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Connecting & Dispatching...
                        </span>
                    </button>
                </div>

                @if($this->testResult)
                    <div class="mt-4 p-4 rounded-xl border {{ $this->testResult['success'] ? 'border-emerald-500/30 bg-emerald-950/20 text-emerald-200' : 'border-rose-500/30 bg-rose-950/20 text-rose-200' }}">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5">
                                @if($this->testResult['success'])
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold {{ $this->testResult['success'] ? 'text-emerald-300' : 'text-rose-300' }}">
                                        {{ $this->testResult['success'] ? 'Probe Handshake Succeeded' : 'Probe Handshake Failed' }}
                                    </span>
                                    <span class="text-[10px] font-mono text-slate-400">{{ $this->testResult['timestamp'] }}</span>
                                </div>
                                <p class="text-xs mt-1 leading-relaxed opacity-90">{{ $this->testResult['message'] }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
