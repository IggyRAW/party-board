<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import RouletteWheel from './RouletteWheel.vue';

const props = defineProps({
    availablePrizes: { type: Array, required: true },
    participants: { type: Array, required: true },
    spinning: { type: Boolean, required: true },
    rotation: { type: Number, required: true },
    winner: { type: String, default: null },
    lastWin: { type: Object, default: null },
});

const emit = defineEmits(['spin', 'close']);

const canSpin = computed(
    () => !props.spinning && props.participants.length > 0 && props.availablePrizes.length > 0,
);

function onKey(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 px-4"
        style="background: var(--scrim); backdrop-filter: blur(8px)"
    >
        <button
            type="button"
            class="absolute top-5 right-5 flex h-10 w-10 items-center justify-center rounded-full text-lg transition-colors"
            style="background: var(--overlay); color: var(--text-subtle)"
            @click="emit('close')"
        >
            ✕
        </button>

        <div class="text-sm font-medium" style="color: var(--text-faint); font-family: Outfit, sans-serif">
            残り景品 {{ availablePrizes.length }} 件 · 参加者 {{ participants.length }} 名
        </div>

        <div class="flex w-full max-w-[1800px] flex-col items-center justify-center gap-6 lg:flex-row lg:gap-14">
            <div class="flex shrink-0 flex-col items-center gap-6">
                <RouletteWheel :items="availablePrizes.map((p) => p.name)" :rotation="rotation" :size="720" />

                <button
                    type="button"
                    class="rounded-2xl px-12 py-4 text-lg font-bold transition-all duration-200"
                    :style="{
                        fontFamily: 'Outfit, sans-serif',
                        background: canSpin ? 'var(--indigo)' : 'rgba(99,102,241,0.25)',
                        color: canSpin ? 'var(--on-accent)' : 'var(--text-faint)',
                        cursor: canSpin ? 'pointer' : 'not-allowed',
                    }"
                    :disabled="!canSpin"
                    @click="emit('spin')"
                >
                    {{ spinning ? '抽選中…' : '🎰 スタート' }}
                </button>
            </div>

            <!-- 当選前から枠を確保しておき、結果が出た瞬間にホイールが横へずれないようにする -->
            <div class="flex w-full items-center justify-center lg:min-h-[460px] lg:w-[42%] lg:min-w-[440px]">
                <div
                    v-if="winner && !spinning && lastWin"
                    class="w-full rounded-3xl px-8 py-10 text-center lg:px-12 lg:py-14"
                    style="background: rgba(99, 102, 241, 0.18); border: 1px solid rgba(99, 102, 241, 0.45)"
                >
                    <div
                        class="mb-4 text-xl font-semibold tracking-widest lg:text-3xl"
                        style="color: var(--indigo-light); font-family: Outfit, sans-serif"
                    >
                        当選者
                    </div>
                    <div
                        class="mb-8 text-5xl leading-tight font-bold break-words lg:text-7xl"
                        style="font-family: Outfit, sans-serif"
                    >
                        🎊 {{ winner }}
                    </div>
                    <div class="text-2xl break-words lg:text-4xl" style="color: var(--text-subtle)">
                        景品: {{ lastWin.prize }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
