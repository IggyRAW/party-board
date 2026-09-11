<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div
        class="rounded-2xl p-5"
        style="background: var(--surface-2); border: 1px solid var(--border)"
    >
        <div class="mb-4 flex items-center justify-between gap-2">
            <h2
                class="text-sm font-semibold tracking-wide uppercase"
                style="font-family: Outfit, sans-serif; color: var(--text-subtle); letter-spacing: 0.08em"
            >
                {{ title }}
            </h2>
            <slot name="action" />
        </div>
        <slot />
    </div>
</template>
