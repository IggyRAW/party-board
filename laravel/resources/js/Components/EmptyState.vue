<script setup>
defineProps({
    text: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="py-8 text-center text-sm" style="color: var(--text-disabled)">
        {{ text }}
    </div>
</template>
