<div
    x-data="{
        toasts: [],
        add(data) {
            const id = Date.now()
            const toast = Array.isArray(data) ? data[0] : data
            this.toasts.push({ id, ...toast })
            setTimeout(() => this.remove(id), toast.duration || 5000)
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id)
        }
    }"
    @toast.window="add($event.detail)"
    class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="flex items-start gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[320px] max-w-md"
            :class="{
                'bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-800': toast.variant === 'success',
                'bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-800': toast.variant === 'danger',
                'bg-amber-50 dark:bg-amber-900/50 border border-amber-200 dark:border-amber-800': toast.variant === 'warning',
                'bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-800': toast.variant === 'info' || !toast.variant
            }"
        >
            <div class="shrink-0 mt-0.5">
                <template x-if="toast.variant === 'success'">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </template>
                <template x-if="toast.variant === 'danger'">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </template>
                <template x-if="toast.variant === 'warning'">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </template>
                <template x-if="toast.variant === 'info' || !toast.variant">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </template>
            </div>

            <div class="flex-1 min-w-0">
                <p
                    class="text-sm font-medium"
                    :class="{
                        'text-green-800 dark:text-green-200': toast.variant === 'success',
                        'text-red-800 dark:text-red-200': toast.variant === 'danger',
                        'text-amber-800 dark:text-amber-200': toast.variant === 'warning',
                        'text-blue-800 dark:text-blue-200': toast.variant === 'info' || !toast.variant
                    }"
                    x-text="toast.heading"
                ></p>
                <p
                    x-show="toast.text"
                    class="text-sm mt-0.5"
                    :class="{
                        'text-green-700 dark:text-green-300': toast.variant === 'success',
                        'text-red-700 dark:text-red-300': toast.variant === 'danger',
                        'text-amber-700 dark:text-amber-300': toast.variant === 'warning',
                        'text-blue-700 dark:text-blue-300': toast.variant === 'info' || !toast.variant
                    }"
                    x-text="toast.text"
                ></p>
            </div>

            <button
                @click="remove(toast.id)"
                class="shrink-0 p-1 rounded hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
                :class="{
                    'text-green-500 hover:text-green-700': toast.variant === 'success',
                    'text-red-500 hover:text-red-700': toast.variant === 'danger',
                    'text-amber-500 hover:text-amber-700': toast.variant === 'warning',
                    'text-blue-500 hover:text-blue-700': toast.variant === 'info' || !toast.variant
                }"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </template>
</div>
