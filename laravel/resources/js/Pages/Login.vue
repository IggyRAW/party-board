<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    login_id: '',
    password: '',
    website: '',
});

function submit() {
    form.post('/login');
}
</script>

<template>
    <div
        class="flex min-h-screen w-full items-center justify-center px-4 py-12"
        style="background: var(--surface); color: var(--text); font-family: Inter, sans-serif"
    >
        <div class="w-full max-w-sm rounded-2xl p-6" style="background: var(--surface-2); border: 1px solid var(--border)">
            <div class="mb-6 flex flex-col items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg text-lg"
                    style="background: var(--indigo)"
                >
                    🎉
                </div>
                <h1 class="text-lg font-bold" style="font-family: Outfit, sans-serif">Party Board</h1>
                <p class="text-xs" style="color: var(--text-muted)">運営用ログイン</p>
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <input
                    v-model="form.website"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                    aria-hidden="true"
                    class="absolute -left-[9999px] h-0 w-0 opacity-0"
                >

                <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-medium" style="color: var(--text-subtle)">ID</span>
                    <input
                        v-model="form.login_id"
                        type="text"
                        autocomplete="username"
                        enterkeyhint="next"
                        class="min-h-11 rounded-lg px-3 py-2 text-sm outline-none"
                        style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                    >
                </label>

                <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-medium" style="color: var(--text-subtle)">パスワード</span>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        enterkeyhint="done"
                        class="min-h-11 rounded-lg px-3 py-2 text-sm outline-none"
                        style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                    >
                </label>

                <p v-if="form.errors.login_id" class="text-xs" style="color: var(--danger)">
                    {{ form.errors.login_id }}
                </p>

                <button
                    type="submit"
                    class="min-h-11 rounded-xl text-sm font-semibold transition-all"
                    :disabled="form.processing"
                    :style="{
                        fontFamily: 'Outfit, sans-serif',
                        background: form.processing ? 'rgba(99,102,241,0.3)' : 'var(--indigo)',
                        color: form.processing ? 'var(--text-faint)' : 'var(--on-accent)',
                    }"
                >
                    {{ form.processing ? '確認中…' : 'ログイン' }}
                </button>
            </form>
        </div>
    </div>
</template>
